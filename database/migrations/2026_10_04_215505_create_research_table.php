<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recherche je Werk (nicht je Aufnahme), damit ein Werk, das beide Nutzer fotografieren, nur einmal
     * recherchiert wird. Betraege in Cent.
     */
    public function up(): void
    {
        Schema::create('research', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->json('sources')->nullable();
            $table->json('existing_guides')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research');
    }
};
