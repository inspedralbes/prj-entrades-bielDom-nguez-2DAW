<?php

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('summary');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('admin_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};
