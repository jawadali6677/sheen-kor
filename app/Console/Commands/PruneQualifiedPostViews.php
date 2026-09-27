<?php

namespace App\Console\Commands;

use App\Models\PostView;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('post-views:prune')]
#[Description('Delete qualified post views older than 35 days.')]
class PruneQualifiedPostViews extends Command
{
    /**
     * Eligibility only reads the last 30 days. Rows older than 35 days are
     * outside that window even if the daily prune is a few days late.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(35)->toDateString();

        $deleted = PostView::query()
            ->where('viewed_on', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} qualified post views older than {$cutoff}.");

        return self::SUCCESS;
    }
}
