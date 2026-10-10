<?php

namespace Database\Seeders;

use App\Models\AiAnalysis;
use App\Models\ConsentRecord;
use App\Models\Message;
use App\Models\SafetyFlag;
use App\Models\SavedResponse;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\TicketCredentialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT DATA ONLY. Every story here is fictional and written for
 * testing; none is based on a real person. No crisis contacts are seeded:
 * those must be added and verified by a person in the dashboard.
 */
class DatabaseSeeder extends Seeder
{
    public function run(TicketCredentialService $credentials): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The development seeder must not run in production. Use php artisan aisa:create-admin.');
        }

        $password = env('DEV_ADMIN_PASSWORD') ?: Str::password(16);
        $admin = User::firstOrCreate(
            ['email' => 'admin@aisa.test'],
            ['name' => 'Dev Admin', 'password' => $password, 'role' => User::ROLE_ADMIN],
        );
        if ($admin->wasRecentlyCreated) {
            $this->command?->warn("Development admin: admin@aisa.test / {$password}  (two-factor setup is required at first login)");
        }

        $this->savedResponses($admin);

        $samples = [
            ['sw', 'family', 'medium', false, 'human', 'Wazazi wangu wananilazimisha niolewe na mtu nisiyempenda. Sijui niwaambie vipi bila kuwaudhi.', 'Person feels pressured by parents to marry someone they do not love.'],
            ['en', 'work', 'low', false, 'ai', 'My manager keeps giving me extra work and I am exhausted, but I need this job.', 'Person is overworked by their manager and worried about job security.'],
            ['mixed', 'relationship', 'medium', false, 'human', 'Nina stress sana because my partner haongei nami tena. We used to be close.', 'Person is distressed that their partner has stopped communicating.'],
            ['en', 'grief', 'medium', false, null, 'My grandmother passed away last month and I cannot focus on anything.', 'Person is grieving their grandmother and struggling to concentrate.'],
            ['sw', 'growth', 'low', false, 'ai', 'Nataka kuanza biashara ndogo lakini naogopa kushindwa.', 'Person wants to start a small business but fears failure.'],
            ['en', 'relationship', 'critical', true, 'human', '[FICTIONAL TEST CASE] He hits me when he is angry and I am scared to go home tonight.', 'Person reports physical abuse by partner and fear of going home.'],
        ];

        foreach ($samples as $i => [$lang, $category, $urgency, $flagged, $mode, $text, $summary]) {
            $created = now()->subHours(6 * ($i + 1));
            $consent = ConsentRecord::create([
                'consent_version' => config('aisa.consent_version'), 'locale' => $lang === 'sw' ? 'sw' : 'en',
                'age_group' => 'adult', 'accepted' => true, 'decided_at' => $created,
            ]);

            $ticket = new Ticket([
                'reference' => $credentials->newReference(), 'consent_record_id' => $consent->id,
                'locale' => $lang === 'sw' ? 'sw' : 'en', 'language' => $lang, 'help_mode' => $mode,
                'status' => $mode === 'human' ? Ticket::STATUS_AWAITING_HUMAN : Ticket::STATUS_OPEN,
                'category' => $category, 'urgency' => $urgency, 'safety_flagged' => $flagged,
                'last_activity_at' => $created,
            ]);
            $ticket->created_at = $created;
            $ticket->forceFill([
                'human_requested_at' => $mode === 'human' ? $created->copy()->addMinutes(10) : null,
                'last_user_message_at' => $created,
            ])->save();
            $credentials->issue($ticket);

            $user = $ticket->messages()->create(['sender' => Message::SENDER_USER, 'body' => $text, 'language' => $lang]);
            $ticket->messages()->create([
                'sender' => Message::SENDER_AI, 'is_mock' => true, 'language' => $lang,
                'body' => '[Simulated reply for development — not a real AI response] Thank you for sharing this with me.',
            ]);
            AiAnalysis::create([
                'ticket_id' => $ticket->id, 'message_id' => $user->id, 'kind' => AiAnalysis::KIND_TURN,
                'category' => $category, 'urgency' => $urgency, 'risk_flag' => $flagged,
                'risk_types' => $flagged ? ['abuse', 'immediate_danger'] : [], 'detected_language' => $lang,
                'summary' => $summary, 'provider' => 'mock', 'model' => 'mock', 'prompt_version' => config('aisa.ai.prompt_version'),
            ]);

            if ($flagged) {
                $ticket->messages()->create(['sender' => Message::SENDER_SYSTEM, 'body' => ConversationService::SAFETY_NOTICE]);
                SafetyFlag::create([
                    'ticket_id' => $ticket->id, 'message_id' => $user->id, 'level' => 'critical',
                    'reasons' => ['abuse', 'immediate_danger'], 'source' => 'both', 'status' => SafetyFlag::STATUS_OPEN,
                    'raised_at' => $created,
                ]);
            }
        }

        $this->command?->info('Seeded '.count($samples).' fictional conversations. Crisis contacts are intentionally not seeded.');
    }

    private function savedResponses(User $admin): void
    {
        $responses = [
            ['Thank you for reaching out', 'en', null, 'Thank you for trusting me with this. I have read everything you wrote, and I want you to know that what you are feeling makes sense.'],
            ['Asante kwa kuwasiliana', 'sw', null, 'Asante kwa kuniamini na jambo hili. Nimesoma yote uliyoandika, na nataka ujue kwamba unachojisikia kina maana.'],
            ['Safety: please reach out now', 'en', null, 'I am concerned for your safety. If you are in danger right now, please contact emergency services or go to someone you trust immediately. The contacts on the "Get help now" page can help you directly, at any time.'],
            ['Usalama: tafadhali omba msaada sasa', 'sw', null, 'Nina wasiwasi kuhusu usalama wako. Ikiwa uko hatarini sasa hivi, tafadhali wasiliana na huduma za dharura au nenda kwa mtu unayemwamini mara moja. Mawasiliano yaliyo kwenye ukurasa wa "Pata msaada sasa" yanaweza kukusaidia moja kwa moja, wakati wowote.'],
        ];

        foreach ($responses as [$title, $language, $category, $body]) {
            SavedResponse::firstOrCreate(['title' => $title], compact('language', 'category', 'body') + ['created_by' => $admin->id]);
        }
    }
}
