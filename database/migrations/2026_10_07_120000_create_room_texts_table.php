<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raumtexte (Sebastian, 07.10.2026): ein Foto vom Saaltext, abgelesen, im Archiv; Aufnahmen koennen einen
 * Raumtext als Kontext mitnehmen (captures.room_text_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_texts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('museum_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->string('title')->nullable();
            $table->text('text')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('cost_cents')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('captures', fn (Blueprint $table) => $table->foreignId('room_text_id')->nullable()->after('place_id')->constrained('room_texts')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('room_text_id');
        });
        Schema::dropIfExists('room_texts');
    }
};
