<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Museum als Archiv-Gruppierung und Recherche-Startpunkt. `research` nimmt ab Etappe 3 das Ergebnis der
     * Museums-Recherche (Sonderausstellungen, Sammlung, Audioguide) auf.
     */
    public function up(): void
    {
        Schema::create('museums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('place_id')->nullable()->unique();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('research')->nullable();
            $table->timestamp('researched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('museums');
    }
};
