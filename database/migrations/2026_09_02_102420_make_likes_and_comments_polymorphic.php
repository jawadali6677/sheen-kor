<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('likes', 'post_id')) {
            if (! Schema::hasColumn('likes', 'likeable_id')) {
                Schema::table('likes', function (Blueprint $table) {
                    $table->string('likeable_type')->nullable()->after('user_id');
                    $table->unsignedBigInteger('likeable_id')->nullable()->after('likeable_type');
                });
            }

            DB::table('likes')
                ->whereNull('likeable_id')
                ->update([
                    'likeable_type' => 'post',
                    'likeable_id' => DB::raw('post_id'),
                ]);

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $this->dropForeignKeysOnColumns('likes', ['post_id', 'user_id']);
            }

            $this->dropIndexIfExists('likes', 'likes_user_id_post_id_unique');

            Schema::table('likes', function (Blueprint $table) {
                $table->dropColumn('post_id');
            });

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                Schema::table('likes', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }
        }

        if (! Schema::hasColumn('likes', 'likeable_id')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->string('likeable_type');
                $table->unsignedBigInteger('likeable_id');
            });
        }

        if (! $this->indexExists('likes', 'likes_likeable_type_likeable_id_index')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->index(['likeable_type', 'likeable_id']);
            });
        }

        if (! $this->indexExists('likes', 'likes_user_id_likeable_id_likeable_type_unique')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->unique(['user_id', 'likeable_id', 'likeable_type']);
            });
        }

        if (Schema::hasColumn('comments', 'post_id')) {
            if (! Schema::hasColumn('comments', 'commentable_id')) {
                Schema::table('comments', function (Blueprint $table) {
                    $table->string('commentable_type')->nullable()->after('user_id');
                    $table->unsignedBigInteger('commentable_id')->nullable()->after('commentable_type');
                });
            }

            DB::table('comments')
                ->whereNull('commentable_id')
                ->update([
                    'commentable_type' => 'post',
                    'commentable_id' => DB::raw('post_id'),
                ]);

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $this->dropForeignKeysOnColumns('comments', ['post_id']);
            }

            Schema::table('comments', function (Blueprint $table) {
                $table->dropColumn('post_id');
            });
        }

        if (! Schema::hasColumn('comments', 'commentable_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->string('commentable_type');
                $table->unsignedBigInteger('commentable_id');
            });
        }

        if (! $this->indexExists('comments', 'comments_commentable_type_commentable_id_index')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->index(['commentable_type', 'commentable_id']);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('likes', 'likes_user_id_likeable_id_likeable_type_unique')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'likeable_id', 'likeable_type']);
            });
        }

        if ($this->indexExists('likes', 'likes_likeable_type_likeable_id_index')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->dropIndex(['likeable_type', 'likeable_id']);
            });
        }

        if (! Schema::hasColumn('likes', 'post_id')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->foreignId('post_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnDelete();
            });

            DB::table('likes')->update([
                'post_id' => DB::raw('likeable_id'),
            ]);
        }

        if (Schema::hasColumn('likes', 'likeable_id')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->dropColumn(['likeable_type', 'likeable_id']);
            });
        }

        if (! $this->indexExists('likes', 'likes_user_id_post_id_unique')) {
            Schema::table('likes', function (Blueprint $table) {
                $table->unique(['user_id', 'post_id']);
            });
        }

        if ($this->indexExists('comments', 'comments_commentable_type_commentable_id_index')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropIndex(['commentable_type', 'commentable_id']);
            });
        }

        if (! Schema::hasColumn('comments', 'post_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreignId('post_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnDelete();
            });

            DB::table('comments')->update([
                'post_id' => DB::raw('commentable_id'),
            ]);
        }

        if (Schema::hasColumn('comments', 'commentable_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropColumn(['commentable_type', 'commentable_id']);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignKeysOnColumns(string $table, array $columns): void
    {
        $foreignKeys = Schema::getForeignKeys($table);

        foreach ($foreignKeys as $foreignKey) {
            $foreignColumns = $foreignKey['columns'] ?? [];

            if (! array_intersect($columns, $foreignColumns)) {
                continue;
            }

            $name = $foreignKey['name'];

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropForeign($name);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        foreach (Schema::getIndexes($table) as $tableIndex) {
            if (($tableIndex['name'] ?? null) !== $index) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index, $tableIndex) {
                if (! empty($tableIndex['unique'])) {
                    $blueprint->dropUnique($index);
                } else {
                    $blueprint->dropIndex($index);
                }
            });

            return;
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $tableIndex) {
            if (($tableIndex['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
