<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('order_line_id')->unique()->constrained('order_lines')->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('qr_payload_ref')->nullable();
            $table->timestampTz('jwt_expires_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
