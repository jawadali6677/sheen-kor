<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

trait PresentsMedia
{
    /**
     * Gallery rows shown with the cover image (report evidence for alerts).
     *
     * @return Collection<int, object>
     */
    abstract protected function galleryMedia(): Collection;

    public function hasMedia(): bool
    {
        return filled($this->featured_image) || $this->galleryMedia()->isNotEmpty();
    }

    /**
     * @return list<array{src: string, type: 'image'|'video', alt: string}>
     */
    public function mediaSlides(): array
    {
        $slides = [];
        $title = (string) ($this->title ?? '');

        if (filled($this->featured_image)) {
            $slides[] = [
                'src' => asset('storage/'.$this->featured_image),
                'type' => 'image',
                'alt' => $title,
            ];
        }

        foreach ($this->galleryMedia() as $media) {
            $slides[] = [
                'src' => asset('storage/'.$media->image),
                'type' => $media->isVideo() ? 'video' : 'image',
                'alt' => (string) ($media->caption ?: $title),
            ];
        }

        return $slides;
    }
}
