<?php

namespace Tests\Feature\Admin;

use App\Models\AiAnalysis;
use App\Models\CrisisResource;
use App\Models\DeletionRequest;
use App\Models\Message;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\DeletionService;
use App\Services\SettingsService;
use App\Services\TicketCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function humanTicket(string $message = 'Please help me think about my family situation.'): Ticket
    {
        $this->fakeAi();
        $ticket = $this->startConversationDirectly($message);
        app(ConversationService::class)->chooseHelp($ticket, 'human');
        app(TicketCredentialService::class)->issue($ticket);

        return $ticket->fresh();
    }

    public function test_every_dashboard_page_renders(): void
    {
        $this->humanTicket();
        $this->actingAsStaff();

        foreach (['admin.dashboard', 'admin.conversations.index', 'admin.safety.index', 'admin.deletions.index', 'admin.contacts.index',
            'admin.saved-responses.index', 'admin.saved-responses.create', 'admin.analytics', 'admin.account',
            'admin.resources.index', 'admin.resources.create', 'admin.settings', 'admin.users.index', 'admin.audit'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_viewing_a_conversation_is_audited(): void
    {
        $ticket = $this->humanTicket();
        $user = $this->actingAsStaff();

        $this->get(route('admin.conversations.show', $ticket))->assertOk()->assertSee('Please help me think about my family situation.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'conversation.viewed', 'subject_type' => 'Ticket', 'subject_id' => $ticket->id, 'user_id' => $user->id]);
    }

    public function test_draft_is_shown_for_review_and_never_sent_automatically(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();

        $this->assertTrue(AiAnalysis::where('ticket_id', $ticket->id)->where('kind', 'draft')->exists());
        $this->assertFalse($ticket->messages()->where('sender', Message::SENDER_HUMAN)->exists());

        $this->get(route('admin.conversations.show', $ticket))->assertSee('AI draft — review, edit or rewrite before sending');
    }

    public function test_reply_requires_explicit_approval(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();

        $this->post(route('admin.conversations.reply', $ticket), ['body' => 'Hello, this is Aisa.'])->assertSessionHasErrors('approved');
        $this->assertFalse($ticket->messages()->where('sender', 'human')->exists());

        $this->post(route('admin.conversations.reply', $ticket), ['body' => 'Hello, this is Aisa.', 'approved' => '1'])
            ->assertRedirect(route('admin.conversations.show', $ticket));

        $reply = $ticket->messages()->where('sender', 'human')->firstOrFail();
        $this->assertNotNull($reply->approved_at);
        $this->assertSame(Ticket::STATUS_ANSWERED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_human_reply_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reply.sent', 'subject_id' => $ticket->id]);
    }

    public function test_reply_body_is_escaped_for_the_user(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();
        $this->post(route('admin.conversations.reply', $ticket), ['body' => '<img src=x onerror=alert(1)>', 'approved' => '1']);

        $this->assertStringNotContainsString('<img src=x', view('public.partials.message', [
            'message' => $ticket->messages()->where('sender', 'human')->first(), 'ticket' => $ticket, 'resources' => collect(),
        ])->render());
    }

    public function test_follow_up_after_answer_returns_to_queue(): void
    {
        $ticket = $this->humanTicket();
        $ticket->forceFill(['status' => Ticket::STATUS_ANSWERED])->save();

        app(ConversationService::class)->handleUserMessage($ticket, 'Thank you, I have one more question.');

        $this->assertSame(Ticket::STATUS_AWAITING_HUMAN, $ticket->fresh()->status);
    }

    public function test_queue_filters(): void
    {
        $this->fakeAi();
        $flagged = $this->startConversationDirectly('I want to end my life');
        $plain = $this->startConversationDirectly('Work is tiring');
        $this->actingAsStaff();

        $this->get(route('admin.conversations.index', ['status' => 'flagged']))
            ->assertSee($flagged->reference)->assertDontSee($plain->reference);
        $this->get(route('admin.conversations.index', ['q' => $plain->reference]))
            ->assertSee($plain->reference)->assertDontSee($flagged->reference);
        $this->get(route('admin.conversations.index', ['language' => 'xx']))->assertSessionHasErrors('language');
    }

    public function test_notes_are_private_and_audited(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();

        $this->post(route('admin.conversations.notes', $ticket), ['note' => 'Check in again on Friday.']);
        $this->assertSame('Check in again on Friday.', $ticket->notes()->first()->body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'note.added']);
        $this->assertDatabaseMissing('audit_logs', ['metadata' => json_encode(['note' => 'Check in again on Friday.'])]);
    }

    public function test_safety_flag_resolution_needs_a_person_notes_and_confirmation(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversationDirectly('I want to end my life');
        $flag = SafetyFlag::firstOrFail();
        $user = $this->actingAsStaff();

        $this->post(route('admin.safety.resolve', $flag), ['outcome' => 'ok'])->assertSessionHasErrors(['outcome', 'reviewed_full']);
        $this->assertSame('open', $flag->fresh()->status);

        $this->post(route('admin.safety.assign', $flag));
        $this->assertSame('in_review', $flag->fresh()->status);

        $this->post(route('admin.safety.resolve', $flag), ['outcome' => 'Replied with stabilising message and help contacts.', 'reviewed_full' => '1']);
        $flag->refresh();
        $this->assertSame('resolved', $flag->status);
        $this->assertSame($user->id, $flag->reviewed_by);
        $this->assertFalse($ticket->fresh()->safety_flagged);
    }

    public function test_safety_panel_lists_critical_flags_first(): void
    {
        $this->fakeAi();
        $high = $this->startConversationDirectly('My husband beats me');
        $critical = $this->startConversationDirectly('I want to kill myself');
        $this->actingAsStaff();

        $this->get(route('admin.safety.index'))->assertSeeInOrder([$critical->reference, $high->reference]);
    }

    public function test_manual_flag(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();

        $this->post(route('admin.conversations.flag', $ticket), ['types' => ['abuse']]);
        $this->assertDatabaseHas('safety_flags', ['ticket_id' => $ticket->id, 'source' => 'manual', 'level' => 'high']);
    }

    public function test_deletion_request_removes_everything_and_keeps_only_the_reference(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversationDirectly('I want to end my life');
        $ticket->notes()->create(['body' => 'note']);
        $request = app(DeletionService::class)->request($ticket);
        $this->actingAsStaff();

        $this->post(route('admin.deletions.complete', $request))->assertRedirect();

        $this->assertNull(Ticket::find($ticket->id));
        $this->assertSame(0, Message::count());
        $this->assertSame(0, AiAnalysis::count());
        $this->assertSame(0, SafetyFlag::count());
        $this->assertDatabaseCount('internal_notes', 0);
        $this->assertSame(DeletionRequest::STATUS_COMPLETED, $request->fresh()->status);
        $this->assertSame($ticket->reference, $request->fresh()->ticket_reference);
    }

    public function test_retention_prune_keeps_unresolved_safety_cases(): void
    {
        $this->fakeAi();
        $old = $this->startConversationDirectly('Work is tiring');
        $oldFlagged = $this->startConversationDirectly('I want to end my life');
        $recent = $this->startConversationDirectly('Hello');
        Ticket::whereIn('id', [$old->id, $oldFlagged->id])->update(['last_activity_at' => now()->subDays(config('aisa.retention_days') + 1)]);

        $this->artisan('aisa:prune')->assertSuccessful();

        $this->assertNull(Ticket::find($old->id));
        $this->assertNotNull(Ticket::find($oldFlagged->id));
        $this->assertNotNull(Ticket::find($recent->id));
    }

    public function test_blocking_a_code_stops_access(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversationDirectly('Hello');
        $code = app(TicketCredentialService::class)->issue($ticket);
        $this->actingAsStaff();

        $this->post(route('admin.conversations.block', $ticket), ['action' => 'block', 'reason' => 'Abusive messages'])->assertRedirect();
        $this->assertTrue($ticket->fresh()->isBlocked());

        auth()->logout();
        $this->post(route('check.store'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_crisis_contacts_need_verification_and_edits_unpublish_them(): void
    {
        $this->actingAsStaff();

        $this->post(route('admin.resources.store'), ['category' => 'emergency', 'name' => 'Test Line', 'phone' => 'call me'])->assertSessionHasErrors('phone');
        $this->post(route('admin.resources.store'), ['category' => 'emergency', 'name' => 'Test Line', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->post(route('admin.resources.store'), ['category' => 'emergency', 'name' => 'Test Line', 'phone' => '+255 000 000'])->assertSessionHasNoErrors();
        $resource = CrisisResource::firstOrFail();
        $this->assertFalse($resource->is_verified);

        $this->post(route('admin.resources.verify', $resource))->assertSessionHasErrors('checked');
        $this->post(route('admin.resources.verify', $resource), ['checked' => '1']);
        $this->assertTrue($resource->fresh()->is_verified);
        $this->get(route('get-help'))->assertSee('Test Line');

        $this->put(route('admin.resources.update', $resource), ['category' => 'emergency', 'name' => 'Test Line', 'phone' => '+255 111 111']);
        $this->assertFalse($resource->fresh()->is_verified);
        $this->get(route('get-help'))->assertDontSee('Test Line');
    }

    public function test_settings_record_approvals_and_launch_readiness(): void
    {
        $this->actingAsStaff();

        $this->post(route('admin.settings.update'), ['approvals' => ['approval_legal_review' => '1'], 'human_requests_open' => '1'])->assertRedirect();
        $this->assertTrue((bool) app(SettingsService::class)->get('approval.legal_review'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $this->artisan('aisa:launch-check')->assertFailed();
    }

    public function test_export_is_admin_only_and_audited(): void
    {
        $ticket = $this->humanTicket('Export me please');

        $this->actingAsStaff(User::factory()->withTwoFactor()->create());
        $this->get(route('admin.conversations.export', $ticket))->assertForbidden();

        $this->actingAsStaff();
        $this->get(route('admin.conversations.export', $ticket))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="aisa-'.$ticket->reference.'.json"')
            ->assertJsonPath('messages.0.body', 'Export me please');
        $this->assertDatabaseHas('audit_logs', ['action' => 'conversation.exported', 'subject_id' => $ticket->id]);
    }

    public function test_admin_delete_requires_typed_reference(): void
    {
        $ticket = $this->humanTicket();
        $this->actingAsStaff();

        $this->post(route('admin.conversations.destroy', $ticket), ['reason' => 'test', 'confirm_reference' => 'nope'])->assertSessionHasErrors('confirm_reference');
        $this->post(route('admin.conversations.destroy', $ticket), ['reason' => 'test', 'confirm_reference' => $ticket->reference])->assertRedirect(route('admin.conversations.index'));
        $this->assertNull(Ticket::find($ticket->id));
    }
}
