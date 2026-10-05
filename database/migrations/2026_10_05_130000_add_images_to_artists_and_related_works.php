<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilder (docs/konzept.md Abschnitt 20): Portraet je Kuenstler, Abbildung je Werk, und Vergleichswerke, auf die
 * der Guide verweist, mit Bild. Nur Links auf Wikimedia Commons, keine Dateien (App bleibt schlank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table): void {
            $table->string('portrait_url', 500)->nullable()->after('short_bio');
            $table->string('portrait_credit', 300)->nullable()->after('portrait_url');
            $table->timestamp('portrait_checked_at')->nullable()->after('portrait_credit');
        });

        Schema::table('artworks', function (Blueprint $table): void {
            $table->string('image_url', 500)->nullable()->after('sources');
            $table->string('image_credit', 300)->nullable()->after('image_url');
            $table->timestamp('image_checked_at')->nullable()->after('image_credit');
        });

        Schema::create('related_works', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('year', 40)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('wikidata_id', 20)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('image_credit', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['artwork_id', 'sort_order'], 'related_works_artwork_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_works');
        Schema::table('artworks', fn (Blueprint $table) => $table->dropColumn(['image_url', 'image_credit', 'image_checked_at']));
        Schema::table('artists', fn (Blueprint $table) => $table->dropColumn(['portrait_url', 'portrait_credit', 'portrait_checked_at']));
    }
};
