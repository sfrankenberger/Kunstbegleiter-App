<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reiter Stadt (docs/konzept.md Abschnitt 23): Orte (Statue, Gebaeude, Platz, Kirche, Denkmal) aus Wikidata oder
 * Places, und Aufnahmen koennen auf einen Ort statt auf ein Werk zeigen (captures.place_id, visit_id dann leer).
 * Recherche kann zu einem Ort gehoeren (research.place_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('kind', 40)->default('other');
            $table->string('wikidata_id', 20)->nullable()->unique();
            $table->string('place_id', 120)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('description', 300)->nullable();
            $table->string('architect')->nullable();
            $table->string('built', 60)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('image_credit', 300)->nullable();
            $table->json('facts')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index(['lat', 'lng']);
        });

        Schema::table('captures', function (Blueprint $table): void {
            $table->foreignId('visit_id')->nullable()->change();
            $table->foreignId('place_id')->nullable()->after('artwork_id')->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable()->after('finished_at');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        Schema::table('research', function (Blueprint $table): void {
            $table->foreignId('artwork_id')->nullable()->change();
            $table->foreignId('place_id')->nullable()->after('artwork_id')->constrained()->nullOnDelete();
        });

        Schema::table('related_works', function (Blueprint $table): void {
            $table->foreignId('artwork_id')->nullable()->change();
            $table->foreignId('place_id')->nullable()->after('artwork_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('related_works', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('place_id');
        });
        Schema::table('research', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('place_id');
        });
        Schema::table('captures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('place_id');
            $table->dropColumn(['lat', 'lng']);
        });
        Schema::dropIfExists('places');
    }
};
