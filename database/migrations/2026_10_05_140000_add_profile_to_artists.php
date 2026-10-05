<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuenstlerprofil (docs/konzept.md Abschnitt 21): einmal je Kuenstler recherchiert, fuer alle Werke genutzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->json('profile')->nullable()->after('short_bio');
            $table->timestamp('profile_checked_at')->nullable()->after('profile');
        });
    }

    public function down(): void
    {
        Schema::table('artists', fn (Blueprint $table) => $table->dropColumn(['profile', 'profile_checked_at']));
    }
};
