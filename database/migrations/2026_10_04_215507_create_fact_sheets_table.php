<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bildschirm-Zusammenfassung je Aufnahme: Kurzfakten, Kernaussagen, "Fuer deine Gaeste", Querverweise.
     */
    public function up(): void
    {
        Schema::create('fact_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->json('key_facts')->nullable();
            $table->json('key_statements')->nullable();
            $table->json('guest_ideas')->nullable();
            $table->json('cross_references')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_sheets');
    }
};
