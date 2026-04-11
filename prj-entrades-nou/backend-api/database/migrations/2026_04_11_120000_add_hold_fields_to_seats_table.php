<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up (): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->uuid('current_hold_id')->nullable()->after('status');
            $table->timestampTz('held_until')->nullable()->after('current_hold_id');
            $table->index(['event_id', 'status']);
        });
    }

    public function down (): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'status']);
            $table->dropColumn(['current_hold_id', 'held_until']);
        });
    }
};
