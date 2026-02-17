<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wow_pets', function (Blueprint $table) {
            $table->integer('id')->primary(); // Blizzard Pet Species ID
            $table->string('name_fr');
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wow_pets');
    }
};