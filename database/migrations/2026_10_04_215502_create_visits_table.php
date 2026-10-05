<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Museumsbesuch als Kontext: 30 Minuten gueltig (`valid_until`), jede Aufnahme verlaengert.
     * `shared_with_user_id` ist der gekoppelte Nutzer bei einem gemeinsamen Besuch (Etappe 5).
     */
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('museum_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('valid_until');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
