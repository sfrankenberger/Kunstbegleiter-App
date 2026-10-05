<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Das Hoerstueck: Skript als JSON-Segmente mit Sprecherrolle, MP3 unter storage/app/private, Kosten in Cent,
     * Rueckmeldung (Daumen, Schwierigkeit) fuer das Vorwissen-Profil.
     */
    public function up(): void
    {
        Schema::create('audio_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->json('script')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('word_count')->nullable();
            $table->string('model')->nullable();
            $table->string('tts_provider', 40)->nullable();
            $table->unsignedInteger('tts_characters')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->string('feedback', 10)->nullable();
            $table->string('difficulty_feedback', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_guides');
    }
};
