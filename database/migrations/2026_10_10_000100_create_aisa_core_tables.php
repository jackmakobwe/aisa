<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core Aisa schema. Columns holding personal text are "text" because they
 * store Laravel-encrypted ciphertext (see the model casts), so they cannot
 * be searched or indexed in SQL by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->string('consent_version', 20);
            $table->string('locale', 5);
            $table->string('age_group', 10); // adult | minor
            $table->boolean('accepted');
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Non-secret reference shown in the dashboard (e.g. "K7Q-M2D").
            $table->string('reference', 12)->unique();
            // HMAC-SHA256 of the secret code. Null until a code is issued.
            $table->char('code_hash', 64)->nullable()->unique();
            $table->timestamp('code_issued_at')->nullable();
            $table->foreignId('consent_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('en');      // interface language
            $table->string('language', 8)->nullable();        // detected: en | sw | mixed
            $table->text('display_name')->nullable();         // encrypted, optional
            $table->boolean('is_minor')->default(false);
            $table->string('help_mode', 10)->nullable();      // ai | human
            $table->string('status', 20)->default('open');    // open | awaiting_human | answered | closed
            $table->string('category', 20)->nullable();
            $table->string('urgency', 10)->nullable();
            $table->boolean('safety_flagged')->default(false);
            $table->timestamp('human_requested_at')->nullable();
            $table->timestamp('first_human_reply_at')->nullable();
            $table->timestamp('last_human_reply_at')->nullable();
            $table->timestamp('last_user_message_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('return_visits')->default(0);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'human_requested_at']);
            $table->index(['safety_flagged', 'status']);
            $table->index('last_activity_at');
            $table->index(['category', 'language']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10); // user | ai | human | system
            $table->longText('body');     // encrypted
            $table->string('language', 8)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // For human replies: when a responder explicitly approved it.
            $table->timestamp('approved_at')->nullable();
            // AI replies produced by the development mock are flagged so they
            // are never mistaken for model output.
            $table->boolean('is_mock')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12); // turn | draft
            $table->string('category', 20)->nullable();
            $table->string('urgency', 10)->nullable();
            $table->boolean('risk_flag')->default(false);
            $table->json('risk_types')->nullable();
            $table->string('detected_language', 8)->nullable();
            $table->text('summary')->nullable();     // encrypted
            $table->longText('draft_reply')->nullable(); // encrypted
            $table->string('provider', 20);
            $table->string('model', 60)->nullable();
            $table->string('prompt_version', 20);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error', 120)->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'kind']);
        });

        Schema::create('safety_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 10);   // concern | high | critical
            $table->json('reasons');       // e.g. ["self_harm","abuse"]
            $table->string('source', 10);  // rules | model | both | minor | manual
            $table->string('status', 12)->default('open'); // open | in_review | resolved
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('raised_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('outcome')->nullable(); // encrypted resolution notes
            $table->timestamps();

            $table->index(['status', 'level', 'raised_at']);
        });

        Schema::create('internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body'); // encrypted
            $table->timestamps();
        });

        Schema::create('saved_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('language', 5); // en | sw
            $table->string('category', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            // Kept after deletion so the request can be shown as completed.
            $table->string('ticket_reference', 12);
            $table->string('status', 12)->default('pending'); // pending | completed | rejected
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // concern | inappropriate | feedback | access | other
            $table->longText('message');        // encrypted
            $table->text('contact')->nullable(); // encrypted, optional
            $table->string('locale', 5);
            $table->string('status', 12)->default('new'); // new | handled
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);  // mail | sms
            $table->string('purpose', 30);  // safety_alert
            $table->string('recipient_hint', 60); // masked, e.g. "s***@aisa.test"
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10);   // sent | failed
            $table->string('error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('crisis_resources', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30); // emergency | mental_health | gbv | child | counselling | community
            $table->string('name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('url', 255)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_sw')->nullable();
            $table->string('hours', 80)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_verified', 'category']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'settings', 'crisis_resources', 'notification_deliveries',
            'contact_submissions', 'deletion_requests', 'saved_responses', 'internal_notes',
            'safety_flags', 'ai_analyses', 'messages', 'tickets', 'consent_records',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
