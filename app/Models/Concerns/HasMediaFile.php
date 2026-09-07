<?php

namespace App\Models\Concerns;

trait HasMediaFile
{
    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }
}
