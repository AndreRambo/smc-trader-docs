<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_zones', function (Blueprint $table) {
            $table->index(['sync_asset_id', 'timeframe', 'display_from', 'display_to'], 'idx_sync_zones_asset_tf_display');
            $table->index(['sync_asset_id', 'timeframe', 'status'], 'idx_sync_zones_asset_tf_status');
            $table->index(['type', 'zone_type'], 'idx_sync_zones_type');
        });
    }

    public function down(): void
    {
        Schema::table('sync_zones', function (Blueprint $table) {
            $table->dropIndex('idx_sync_zones_asset_tf_display');
            $table->dropIndex('idx_sync_zones_asset_tf_status');
            $table->dropIndex('idx_sync_zones_type');
        });
    }
};
