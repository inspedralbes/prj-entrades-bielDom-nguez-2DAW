<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('external_tm_id')->nullable()->index();
            $table->string('name');
            $table->unsignedSmallInteger('hold_ttl_seconds')->default(240);
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('hidden_at')->nullable();
            $table->string('category')->nullable();
            $table->json('seat_layout')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
