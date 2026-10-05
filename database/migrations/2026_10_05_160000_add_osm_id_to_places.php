<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orte aus OpenStreetMap und Google Places ohne Wikidata-Eintrag (docs/konzept.md Abschnitt 24): Schluessel osm_id
 * ("node/123", "way/456") bzw. place_id (Google).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->string('osm_id', 40)->nullable()->after('wikidata_id');
            $table->index('osm_id');
            $table->index('place_id');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropIndex(['osm_id']);
            $table->dropIndex(['place_id']);
            $table->dropColumn('osm_id');
        });
    }
};
