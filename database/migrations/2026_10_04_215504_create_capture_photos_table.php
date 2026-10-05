<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die einzelnen Fotos einer Aufnahme. Datei unter storage/app/private/captures/{capture}/, Typ artwork, label
     * oder room_text (App\Enums\PhotoType).
     */
    public function up(): void
    {
        Schema::create('capture_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('type', 20)->default('artwork');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->text('ocr_text')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_photos');
    }
};
