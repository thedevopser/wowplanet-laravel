<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('localized_names');
    }

    public function down(): void
    {
        Schema::create('localized_names', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedInteger('blizzard_id');
            $table->string('locale', 10)->default('fr_FR');
            $table->string('name');
            $table->timestamps();
            $table->unique(['type', 'blizzard_id', 'locale']);
        });
    }
};
