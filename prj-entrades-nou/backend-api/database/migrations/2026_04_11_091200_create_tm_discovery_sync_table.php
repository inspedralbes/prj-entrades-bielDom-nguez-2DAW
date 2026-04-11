<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tm_discovery_sync', function (Blueprint $table) {
            $table->id();
            $table->string('cursor')->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_discovery_sync');
    }
};
