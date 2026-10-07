<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abgelesene Werk- und Raumtexte bleiben als Text an der Aufnahme, die Fotos dazu werden nach dem Ablesen
 * geloescht (Sebastian, 07.10.2026: "es reicht, wenn die als Text hinterlegt sind").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captures', fn (Blueprint $table) => $table->text('label_text')->nullable()->after('recognition'));
    }

    public function down(): void
    {
        Schema::table('captures', fn (Blueprint $table) => $table->dropColumn('label_text'));
    }
};
