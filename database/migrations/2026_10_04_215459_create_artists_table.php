<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sort_name')->nullable();
            $table->smallInteger('born_year')->nullable();
            $table->smallInteger('died_year')->nullable();
            $table->string('wikidata_id', 20)->nullable()->unique();
            $table->string('gnd_id', 20)->nullable();
            $table->text('short_bio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
