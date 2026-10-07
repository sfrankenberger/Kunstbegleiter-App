<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beide Stufen je Aufnahme aufheben (Sebastian, 07.10.2026): quick = Schnellstufe, full = vertiefender Guide.
 * Bestand: bei Aufnahmen im Modus full ist der jeweils letzte Guide und das letzte Fact Sheet der vertiefende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_guides', fn (Blueprint $table) => $table->string('kind', 10)->default('quick')->after('capture_id'));
        Schema::table('fact_sheets', fn (Blueprint $table) => $table->string('kind', 10)->default('quick')->after('capture_id'));

        foreach (DB::table('captures')->where('mode', 'full')->pluck('id') as $captureId) {
            foreach (['audio_guides', 'fact_sheets'] as $table) {
                $latest = DB::table($table)->where('capture_id', $captureId)->max('id');

                if ($latest !== null) {
                    DB::table($table)->where('id', $latest)->update(['kind' => 'full']);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('audio_guides', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('fact_sheets', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
