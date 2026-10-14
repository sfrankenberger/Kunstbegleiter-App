<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wiederverwendete vertiefende Guides (14.10.2026): Verweis auf den Guide, von dem Text und Audio stammen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_guides', fn (Blueprint $table) => $table->unsignedBigInteger('source_guide_id')->nullable()->after('kind'));
    }

    public function down(): void
    {
        Schema::table('audio_guides', fn (Blueprint $table) => $table->dropColumn('source_guide_id'));
    }
};
