<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional accounts for people who use the public site ("members").
 * Kept separate from `users`, which holds staff only, so a member can never
 * be mistaken for a responder or reach the dashboard.
 *
 * The email address is stored encrypted. Lookups use email_hash, a keyed
 * hash, so a database leak alone does not reveal who has an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->text('name')->nullable();               // encrypted, optional nickname
            $table->text('email');                          // encrypted
            $table->char('email_hash', 64)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();         // null for Google-only accounts
            $table->char('google_id_hash', 64)->nullable()->unique();
            $table->string('locale', 5)->default('en');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Password reset tokens for members, keyed by email_hash (never the address).
        Schema::create('member_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('consent_record_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
        Schema::dropIfExists('member_password_reset_tokens');
        Schema::dropIfExists('members');
    }
};
