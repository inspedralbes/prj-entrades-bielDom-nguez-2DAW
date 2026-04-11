<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('external_seat_key');
            $table->string('status')->default('available')->index();
            $table->timestamps();

            $table->unique(['event_id', 'external_seat_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
