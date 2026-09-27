<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fresh installs seed 5000 in the original settings migration. Lower that
     * untouched default to 0 so nobody is blocked until an admin raises it.
     * A value an admin already changed is left alone.
     */
    public function up(): void
    {
        DB::table('monetization_settings')
            ->where('key', 'eligibility_min_qualified_views_30d')
            ->where('value', '5000')
            ->update([
                'value' => '0',
                'updated_at' => now(),
            ]);
    }

    /**
     * Restores the previous seeded default. An admin who chose 0 cannot be
     * distinguished from this migration, so a rollback also changes that row.
     */
    public function down(): void
    {
        DB::table('monetization_settings')
            ->where('key', 'eligibility_min_qualified_views_30d')
            ->where('value', '0')
            ->update([
                'value' => '5000',
                'updated_at' => now(),
            ]);
    }
};
