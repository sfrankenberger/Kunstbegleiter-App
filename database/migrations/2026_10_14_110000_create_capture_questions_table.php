<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachfragen zu einer Aufnahme (Sebastian, 14.10.2026): Frage, kurze Antwort, gesprochene Antwort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('cost_cents')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_questions');
    }
};
