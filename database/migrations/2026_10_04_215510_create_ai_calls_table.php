<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kostenprotokoll je KI-Aufruf (Anthropic und TTS): Grundlage fuer das Monatslimit je Nutzer und die
     * Kostenuebersicht im Admin. Betraege in Cent. Kein Papierkorb (Protokoll).
     */
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('capture_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 30);
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('characters')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('succeeded')->default(true);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
