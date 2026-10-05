<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stufe je Aufnahme (quick, full) und Standard je Nutzer (docs/konzept.md Abschnitt 11). Nur neue Spalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captures', function (Blueprint $table): void {
            $table->string('mode', 10)->default('quick')->after('length');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('default_mode', 10)->default('quick')->after('preferred_length');
        });
    }

    public function down(): void
    {
        Schema::table('captures', fn (Blueprint $table) => $table->dropColumn('mode'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('default_mode'));
    }
};
