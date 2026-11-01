<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom ini dipakai spatie/laravel-activitylog 4.x. Tabel lama dibuat oleh
     * migrasi versi 5.x yang tidak menyertakan batch_uuid.
     */
    public function up(): void
    {
        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
};
