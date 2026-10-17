<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crisis_resources', function (Blueprint $table) {
            // Where the service is. A null region means it is national (e.g. a hotline).
            $table->string('address', 255)->nullable()->after('url');
            $table->string('district', 80)->nullable()->after('address');
            $table->string('region', 40)->nullable()->after('district');
            $table->decimal('latitude', 9, 6)->nullable()->after('region');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
            // For the person verifying: where the details came from. Never shown publicly.
            $table->string('source_note', 500)->nullable()->after('hours');

            $table->index(['is_verified', 'region']);
        });
    }

    public function down(): void
    {
        Schema::table('crisis_resources', function (Blueprint $table) {
            $table->dropIndex(['is_verified', 'region']);
            $table->dropColumn(['address', 'district', 'region', 'latitude', 'longitude', 'source_note']);
        });
    }
};
