<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Entry point the person chose on the home page (config aisa.modules).
            $table->string('module', 12)->nullable()->after('category');
            $table->index('module');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            // Ids of the knowledge notes given to the model for this answer.
            $table->json('knowledge_refs')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropColumn('knowledge_refs');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropColumn('module');
        });
    }
};
