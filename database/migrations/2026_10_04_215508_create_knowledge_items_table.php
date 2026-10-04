<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lerngedaechtnis je Nutzer: was zu einem Kuenstler, einer Epoche oder einem Thema (Morph `subject`) schon
     * erzaehlt wurde, verhindert Wiederholungen (Etappe 4).
     */
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('capture_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary');
            $table->timestamp('told_at');
            $table->timestamps();

            $table->index(['user_id', 'subject_type', 'subject_id'], 'knowledge_items_user_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
