<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Das Kunstwerk, einmal angelegt und von allen Nutzern geteilt. `facts` und `sources` sind JSON aus der
     * Erkennung und Recherche (Etappe 3).
     */
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('epoch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('museum_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('dating')->nullable();
            $table->string('technique')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('inventory_number')->nullable();
            $table->string('wikidata_id', 20)->nullable();
            $table->json('facts')->nullable();
            $table->json('sources')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['museum_id', 'inventory_number'], 'artworks_museum_inventory_index');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
