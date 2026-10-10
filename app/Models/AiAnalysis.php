<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suggestions produced by the AI layer. They are only suggestions: a
 * responder confirms category and urgency, and drafts are never sent
 * automatically.
 */
class AiAnalysis extends Model
{
    public const KIND_TURN = 'turn';

    public const KIND_DRAFT = 'draft';

    protected $fillable = [
        'ticket_id', 'message_id', 'kind', 'category', 'urgency', 'risk_flag', 'risk_types',
        'detected_language', 'summary', 'draft_reply', 'provider', 'model', 'prompt_version',
        'input_tokens', 'output_tokens', 'latency_ms', 'error',
    ];

    protected function casts(): array
    {
        return [
            'risk_flag' => 'boolean',
            'risk_types' => 'array',
            'summary' => 'encrypted',
            'draft_reply' => 'encrypted',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
