<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qualified views are logged-in viewers only, deduped on a rolling 24 hours.
     *
     * Guest rows (viewer_key guest:*) are deleted: guests no longer count, and
     * eligibility ignores any leftover row whose viewer_user_id is null.
     * Existing signed-in rows are kept and linked to viewer_user_id parsed from
     * viewer_key (user:{id}) when that account still exists. Rows whose viewer
     * account is gone are deleted. The calendar-day unique index is removed
     * because two views on different dates can still fall inside 24 hours;
     * historical rows are left as they were recorded. A rollback cannot
     * restore deleted guest rows, and it can fail if newer rolling-window rows
     * would violate the old calendar-day unique key.
     */
    public function up(): void
    {
        DB::table('post_views')->where('viewer_key', 'like', 'guest:%')->delete();

        Schema::table('post_views', function (Blueprint $table) {
            $table->dropUnique(['post_id', 'viewer_key', 'viewed_on']);
        });

        Schema::table('post_views', function (Blueprint $table) {
            $table->foreignId('viewer_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        $integerCast = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? 'UNSIGNED'
            : 'INTEGER';

        DB::statement(<<<SQL
            UPDATE post_views
            SET viewer_user_id = CAST(substr(viewer_key, 6) AS {$integerCast})
            WHERE viewer_key LIKE 'user:%'
              AND CAST(substr(viewer_key, 6) AS {$integerCast}) IN (SELECT id FROM users)
        SQL);

        DB::table('post_views')->whereNull('viewer_user_id')->delete();

        Schema::table('post_views', function (Blueprint $table) {
            $table->index(['post_id', 'viewer_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_views', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'viewer_user_id', 'created_at']);
            $table->dropConstrainedForeignId('viewer_user_id');
        });

        Schema::table('post_views', function (Blueprint $table) {
            $table->unique(['post_id', 'viewer_key', 'viewed_on']);
        });
    }
};
