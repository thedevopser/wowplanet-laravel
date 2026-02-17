<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_achievements', function (Blueprint $table) {
            $table->integer('id')->primary(); // Blizzard Achievement ID
            $table->string('name_fr');
            $table->tinyInteger('expansion_id'); // 0-11
            $table->string('category_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['expansion_id', 'category_name']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_achievements');
    }
};