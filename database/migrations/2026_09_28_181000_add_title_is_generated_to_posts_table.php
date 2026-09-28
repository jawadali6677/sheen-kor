<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status-style posts store a generated title so other screens can keep
     * reading post->title. Article posts stay false.
     *
     * A non-null boolean with a constant default is valid on MySQL and SQLite.
     * It does not change enums, foreign keys, or indexes, and it does not use
     * doctrine/dbal column changes. Existing rows become false.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('title_is_generated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('title_is_generated');
        });
    }
};
