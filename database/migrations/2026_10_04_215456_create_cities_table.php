<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code', 2)->default('AT');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->unique(['name', 'country_code'], 'cities_name_country_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
