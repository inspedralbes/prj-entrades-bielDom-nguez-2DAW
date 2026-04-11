<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up (): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('hold_uuid')->nullable()->after('event_id')->unique();
        });
    }

    public function down (): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['hold_uuid']);
            $table->dropColumn('hold_uuid');
        });
    }
};
