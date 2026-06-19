<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_zones', 'display_from')) {
                $table->dateTime('display_from')->nullable()->after('mitigated_at');
            }
            if (!Schema::hasColumn('sync_zones', 'display_to')) {
                $table->dateTime('display_to')->nullable()->after('display_from');
            }
            if (!Schema::hasColumn('sync_zones', 'payload')) {
                $table->json('payload')->nullable()->after('display_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_zones', function (Blueprint $table) {
            $table->dropColumn(['display_from', 'display_to', 'payload']);
        });
    }
};
