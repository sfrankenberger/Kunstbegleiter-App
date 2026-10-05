<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pipeline (Etappe 3): `step` ist der laufende Schritt fuer die Fortschrittsanzeige (recognizing, researching,
     * writing, checking, speaking), `needs_confirmation` verlangt die Bestaetigung der Erkennung (Sicherheit unter
     * dem Schwellwert), `script_model` haelt fest, ob das Premium-Modell gewaehlt war.
     */
    public function up(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->string('step', 20)->nullable()->after('status');
            $table->boolean('needs_confirmation')->default(false)->after('recognition');
            $table->boolean('premium')->default(false)->after('length');
            $table->timestamp('finished_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->dropColumn(['step', 'needs_confirmation', 'premium', 'finished_at']);
        });
    }
};
