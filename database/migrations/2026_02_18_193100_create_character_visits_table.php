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
        Schema::create('character_visits', function (Blueprint $table) {
            $table->id();
            $table->string('realm_slug', 100);
            $table->string('character_name', 100);
            $table->string('display_name', 100)->nullable();
            $table->string('display_realm', 100)->nullable();
            $table->string('class_name', 50)->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->timestamp('last_visited_at');
            $table->timestamps();

            $table->unique(['realm_slug', 'character_name']);
            $table->index('last_visited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_visits');
    }
};
