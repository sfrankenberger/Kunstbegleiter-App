<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kopplung zweier Nutzer fuer gemeinsame Besuche (docs/plan-etappe-1.md Abschnitt 4): user_a_id ist immer die
     * kleinere ID, damit das Paar nur einmal vorkommt.
     */
    public function up(): void
    {
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('requested');
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_a_id', 'user_b_id'], 'pairings_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairings');
    }
};
