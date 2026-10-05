<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Analyse aus 1 bis 3 Fotos. Status: uploaded, recognized, researched, done, failed (App\Enums\CaptureStatus).
     * `recognition` ist das JSON der Erkennung (Titel, Kuenstler, Datierung, Sicherheit, Alternativen).
     */
    public function up(): void
    {
        Schema::create('captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('uploaded');
            $table->string('length', 20)->default('normal');
            $table->json('recognition')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visit_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captures');
    }
};
