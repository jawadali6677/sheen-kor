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
     * historical rows are left as they were recorded.
     *
     * On MySQL/InnoDB that unique index is the only index whose leftmost column
     * is post_id, so it backs the post_id foreign key and cannot be dropped
     * until another post_id-leading index exists. The (post_id, viewer_user_id,
     * created_at) index is created first for that reason; it also serves the
     * rolling-24-hour dedupe lookup. Each step is guarded so a partial run can
     * be retried: MySQL commits every DDL statement, and this migration is not
     * recorded if a later statement fails.
     *
     * A rollback cannot restore deleted guest rows, and it can fail if newer
     * rolling-window rows would violate the old calendar-day unique key.
     * Rollback recreates that unique index before dropping the helper index,
     * and drops the viewer_user_id foreign key before the column.
     */
    public function up(): void
    {
        DB::table('post_views')->where('viewer_key', 'like', 'guest:%')->delete();

        if (! Schema::hasColumn('post_views', 'viewer_user_id')) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->foreignId('viewer_user_id')->nullable()->after('user_id');
            });
        }

        $this->linkLoggedInViewers();

        DB::table('post_views')->whereNull('viewer_user_id')->delete();

        if (! Schema::hasForeignKey('post_views', ['viewer_user_id'])) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->foreign('viewer_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('post_views', ['post_id', 'viewer_user_id', 'created_at'])) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->index(['post_id', 'viewer_user_id', 'created_at']);
            });
        }

        if (Schema::hasIndex('post_views', ['post_id', 'viewer_key', 'viewed_on'], 'unique')) {
            if (! $this->postIdHasSupportingIndexBesidesCalendarUnique()) {
                throw new RuntimeException(
                    'Cannot drop post_views_post_id_viewer_key_viewed_on_unique until another index leading with post_id exists.'
                );
            }

            Schema::table('post_views', function (Blueprint $table) {
                $table->dropUnique(['post_id', 'viewer_key', 'viewed_on']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('post_views', ['post_id', 'viewer_key', 'viewed_on'], 'unique')) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->unique(['post_id', 'viewer_key', 'viewed_on']);
            });
        }

        if (Schema::hasForeignKey('post_views', ['viewer_user_id'])) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->dropForeign(['viewer_user_id']);
            });
        }

        $this->dropSecondaryIndexesReferencing('post_views', 'viewer_user_id');

        if (Schema::hasColumn('post_views', 'viewer_user_id')) {
            Schema::table('post_views', function (Blueprint $table) {
                $table->dropColumn('viewer_user_id');
            });
        }
    }

    /**
     * Point signed-in rows at the account encoded in viewer_key.
     *
     * Rows that are already linked are left unchanged so a retried migration
     * does not rewrite them.
     */
    private function linkLoggedInViewers(): void
    {
        if (! Schema::hasColumn('post_views', 'viewer_user_id')) {
            return;
        }

        $integerCast = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? 'UNSIGNED'
            : 'INTEGER';

        DB::statement(<<<SQL
            UPDATE post_views
            SET viewer_user_id = CAST(substr(viewer_key, 6) AS {$integerCast})
            WHERE viewer_user_id IS NULL
              AND viewer_key LIKE 'user:%'
              AND CAST(substr(viewer_key, 6) AS {$integerCast}) IN (SELECT id FROM users)
        SQL);
    }

    /**
     * InnoDB will not drop the calendar-day unique index while it is the only
     * index that can satisfy the post_id foreign key.
     */
    private function postIdHasSupportingIndexBesidesCalendarUnique(): bool
    {
        foreach (Schema::getIndexes('post_views') as $index) {
            if (($index['columns'][0] ?? null) !== 'post_id') {
                continue;
            }

            if (($index['unique'] ?? false) && $index['columns'] === ['post_id', 'viewer_key', 'viewed_on']) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Drop non-primary indexes that still mention a column, using the names the
     * database reports. MySQL names the index it creates for a foreign key
     * after that key, which is not the name dropIndex() would guess from the
     * column list. The caller must already have another index in place for any
     * foreign key those indexes were supporting.
     */
    private function dropSecondaryIndexesReferencing(string $table, string $column): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] || ! in_array($column, $index['columns'], true)) {
                continue;
            }

            $name = $index['name'];
            $unique = $index['unique'];

            Schema::table($table, function (Blueprint $blueprint) use ($name, $unique): void {
                if ($unique) {
                    $blueprint->dropUnique($name);
                } else {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
