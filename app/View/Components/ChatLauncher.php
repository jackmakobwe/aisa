<?php

namespace App\View\Components;

use App\Models\CrisisResource;
use App\Models\Message;
use App\Services\ConversationService;
use App\Services\LaunchReadiness;
use App\Services\TicketAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * Floating "chat with the assistant" button on public pages. With an open
 * conversation in this session it continues it in a panel; otherwise the
 * panel collects a first message, which still goes through the normal
 * consent step before anything is stored or sent to the AI.
 */
class ChatLauncher extends Component
{
    /** Pages that already are the conversation or a step of starting one. */
    private const HIDDEN_ON = [
        'conversation.*', 'consent.*', 'help.*', 'code.*', 'check.*', 'not-open', 'under-18', 'get-help',
    ];

    /** How many recent messages the panel shows. */
    private const RECENT = 8;

    public bool $visible;

    public ?\App\Models\Ticket $ticket = null;

    /** @var Collection<int, Message> */
    public Collection $messages;

    public Collection $resources;

    public function __construct(TicketAccess $access, LaunchReadiness $readiness)
    {
        $this->messages = collect();
        $this->resources = collect();
        $this->visible = ! request()->routeIs(...self::HIDDEN_ON)
            && ! (app()->isProduction() && ! $readiness->isReady());

        if (! $this->visible) {
            return;
        }

        $this->ticket = $access->current();
        if ($this->ticket) {
            $this->messages = $this->ticket->messages()->reorder()->latest('id')->limit(self::RECENT)->get()->reverse()->values();
            $needsResources = $this->messages->contains(fn (Message $m) => $m->sender === Message::SENDER_SYSTEM && $m->body === ConversationService::SAFETY_NOTICE);
            $this->resources = $needsResources ? CrisisResource::verified()->orderBy('sort_order')->get() : collect();
        }
    }

    public function shouldRender(): bool
    {
        return $this->visible;
    }

    public function render(): View
    {
        return view('components.chat-launcher');
    }
}
