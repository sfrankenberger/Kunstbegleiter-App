<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufnahmen aus der Offline-Warteschlange des Handys (14.10.2026): eine vom Handy vergebene ID, damit ein
 * wiederholtes Senden nach Funkloch keine zweite Aufnahme anlegt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captures', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->after('user_id');
            $table->unique(['user_id', 'client_id'], 'captures_user_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table): void {
            $table->dropUnique('captures_user_client_unique');
            $table->dropColumn('client_id');
        });
    }
};
