<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abschnitte im Fact Sheet (docs/konzept.md Abschnitt 14): Kuenstler, Provenienz, Deutung, Epoche, Zitat,
 * Anekdote, Kuratorenstimme, Weiteres. Nur eine neue Spalte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fact_sheets', function (Blueprint $table): void {
            $table->json('sections')->nullable()->after('cross_references');
        });
    }

    public function down(): void
    {
        Schema::table('fact_sheets', fn (Blueprint $table) => $table->dropColumn('sections'));
    }
};
