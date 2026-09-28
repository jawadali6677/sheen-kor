<?php

namespace Tests\Feature;

use App\Actions\ModerateContent;
use App\Enums\ModerationDecision;
use ArrayObject;
use Carbon\CarbonInterval;
use GdImage;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class ModerateContentTest extends TestCase
{
    public function test_large_image_is_sent_downscaled_for_moderation(): void
    {
        $path = $this->patternedJpeg('park-day.jpg', 2000, 1500);
        $original = file_get_contents($path);
        $this->assertIsString($original);
        $logged = $this->captureLogs();

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Allow, $decision);
            $this->assertSame(hash('sha256', $original), hash_file('sha256', $path));
            $this->assertSame([], $this->warningReasons($logged));

            $request = $this->soleImageRequest();
            $payload = $this->attachedMedia($request);
            $info = getimagesizefromstring($payload);

            $this->assertNotFalse($info);
            $this->assertSame(IMAGETYPE_JPEG, $info[2]);
            $this->assertLessThanOrEqual(1280, $info[0]);
            $this->assertLessThanOrEqual(1280, $info[1]);
            $this->assertLessThan(strlen($original), strlen($payload));
            $this->assertSame('park-day.jpg', $this->attachedFilename($request));
            $this->assertSame('nudity-2.1,offensive,gore-2.0,violence', $this->formField($request, 'models'));
        } finally {
            $this->deleteTempFile($path);
        }
    }

    public function test_image_moderation_sends_the_original_file_when_it_cannot_be_resized(): void
    {
        $path = $this->tempPath('notes.jpg');
        $original = str_repeat('not-a-photo-', 4000);
        file_put_contents($path, $original);

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Allow, $decision);
            $this->assertSame($original, file_get_contents($path));

            $request = $this->soleImageRequest();

            $this->assertSame($original, $this->attachedMedia($request));
            $this->assertSame('notes.jpg', $this->attachedFilename($request));
        } finally {
            $this->deleteTempFile($path);
        }
    }

    public function test_image_moderation_allows_content_after_a_connection_timeout_retry(): void
    {
        $path = $this->tinyJpeg('flower.jpg');
        Sleep::fake();
        $logged = $this->captureLogs();

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::sequence()
                    ->pushFailedConnection('cURL error 28: Operation timed out')
                    ->push($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Allow, $decision);
            Http::assertSentCount(2);
            $this->assertSame([], $this->warningReasons($logged));
            Sleep::assertSlept(fn (CarbonInterval $duration): bool => (int) $duration->totalMicroseconds === 200000);
        } finally {
            Sleep::fake(false);
            $this->deleteTempFile($path);
        }
    }

    public function test_image_moderation_stays_in_review_after_repeated_timeouts(): void
    {
        $path = $this->tinyJpeg('flower.jpg');
        Sleep::fake();
        $logged = $this->captureLogs();

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::failedConnection('cURL error 28: Operation timed out for test-secret'),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Review, $decision);
            Http::assertSentCount(2);
            $this->assertLoggedWarning($logged, 'image', 'cURL error 28: Operation timed out for [redacted]');
            Sleep::assertSleptTimes(1);
        } finally {
            Sleep::fake(false);
            $this->deleteTempFile($path);
        }
    }

    public function test_failed_image_moderation_response_logs_a_warning_and_stays_in_review(): void
    {
        $path = $this->tinyJpeg('flower.jpg');
        $logged = $this->captureLogs();

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response(['status' => 'failure'], 500),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Review, $decision);
            Http::assertSentCount(1);
            $this->assertLoggedWarning($logged, 'image', 'HTTP 500');
        } finally {
            $this->deleteTempFile($path);
        }
    }

    public function test_unsuccessful_image_moderation_status_logs_a_warning_without_the_api_secret(): void
    {
        $path = $this->tinyJpeg('flower.jpg');
        $logged = $this->captureLogs();

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response([
                    'status' => 'failure',
                    'error' => ['message' => 'invalid api_secret test-secret'],
                ]),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Review, $decision);
            Http::assertSentCount(1);
            $this->assertLoggedWarning($logged, 'image', 'invalid api_secret [redacted]');
        } finally {
            $this->deleteTempFile($path);
        }
    }

    public function test_transparent_png_is_flattened_onto_white_and_downscaled(): void
    {
        $path = $this->noisyTransparentPng('canopy.png');
        $original = file_get_contents($path);
        $this->assertIsString($original);

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Allow, $decision);
            $this->assertSame(hash('sha256', $original), hash_file('sha256', $path));

            $request = $this->soleImageRequest();
            $payload = $this->attachedMedia($request);
            $info = getimagesizefromstring($payload);
            $image = imagecreatefromstring($payload);

            $this->assertNotFalse($info);
            $this->assertSame(IMAGETYPE_JPEG, $info[2]);
            $this->assertLessThanOrEqual(1280, $info[0]);
            $this->assertLessThanOrEqual(1280, $info[1]);
            $this->assertLessThan(strlen($original), strlen($payload));
            $this->assertSame('canopy.jpg', $this->attachedFilename($request));
            $this->assertInstanceOf(GdImage::class, $image);
            $this->assertSame(['red' => 255, 'green' => 255, 'blue' => 255], $this->rgbAt($image, 1, 1));
            $center = $this->rgbAt($image, intdiv($info[0], 2), intdiv($info[1], 2));
            $this->assertGreaterThan(200, $center['green']);
            $this->assertLessThan(40, $center['red']);
            imagedestroy($image);
        } finally {
            $this->deleteTempFile($path);
        }
    }

    #[RequiresPhpExtension('exif')]
    public function test_exif_orientation_is_applied_before_the_image_is_downscaled(): void
    {
        $path = $this->patternedJpeg('rotated.jpg', 1800, 900);
        $stored = getimagesize($path);
        $this->assertNotFalse($stored);
        $this->assertGreaterThan($stored[1], $stored[0]);
        $jpeg = file_get_contents($path);
        $this->assertIsString($jpeg);
        file_put_contents($path, $this->jpegWithExifOrientation($jpeg, 6));
        $original = file_get_contents($path);
        $this->assertIsString($original);

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/check.json' => Http::response($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [$path], []);

            $this->assertSame(ModerationDecision::Allow, $decision);
            $this->assertSame(hash('sha256', $original), hash_file('sha256', $path));

            $info = getimagesizefromstring($this->attachedMedia($this->soleImageRequest()));

            $this->assertNotFalse($info);
            $this->assertLessThanOrEqual(1280, $info[0]);
            $this->assertLessThanOrEqual(1280, $info[1]);
            $this->assertGreaterThan($info[0], $info[1]);
        } finally {
            $this->deleteTempFile($path);
        }
    }

    public function test_video_moderation_sends_the_original_file(): void
    {
        $path = $this->patternedJpeg('clip.mp4', 1800, 900);
        $original = file_get_contents($path);
        $this->assertIsString($original);

        try {
            $this->withSightengineCredentials();
            Http::preventStrayRequests();
            Http::fake([
                'api.sightengine.com/1.0/video/check-sync.json' => Http::response($this->safeImagePayload()),
            ]);

            $decision = (new ModerateContent)->handle('', [], [$path]);

            $this->assertSame(ModerationDecision::Allow, $decision);
            Http::assertSentCount(1);
            $this->assertSame($original, file_get_contents($path));

            $recorded = Http::recorded(
                fn (Request $request): bool => str_contains($request->url(), '/1.0/video/check-sync.json'),
            );

            $this->assertCount(1, $recorded);
            $this->assertSame($original, $this->attachedMedia($recorded->first()[0]));
            $this->assertSame('clip.mp4', $this->attachedFilename($recorded->first()[0]));
        } finally {
            $this->deleteTempFile($path);
        }
    }

    private function withSightengineCredentials(): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeImagePayload(): array
    {
        return [
            'status' => 'success',
            'nudity' => [
                'sexual_activity' => 0.01,
                'sexual_display' => 0.01,
                'erotica' => 0.01,
                'very_suggestive' => 0.05,
            ],
            'offensive' => ['prob' => 0.01],
            'gore' => ['prob' => 0.01],
            'violence' => ['prob' => 0.01],
        ];
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureLogs(): ArrayObject
    {
        $logged = new ArrayObject;

        Log::listen(function (MessageLogged $event) use ($logged): void {
            $logged->append($event);
        });

        return $logged;
    }

    /**
     * @param  ArrayObject<int, MessageLogged>  $logged
     * @return list<string|null>
     */
    private function warningReasons(ArrayObject $logged): array
    {
        $reasons = [];

        foreach ($logged as $event) {
            if ($event->level === 'warning' && $event->message === 'Sightengine moderation check failed') {
                $reason = $event->context['reason'] ?? null;
                $reasons[] = is_string($reason) ? $reason : null;
            }
        }

        return $reasons;
    }

    /**
     * @param  ArrayObject<int, MessageLogged>  $logged
     */
    private function assertLoggedWarning(ArrayObject $logged, string $check, string $reason): void
    {
        $matches = [];

        foreach ($logged as $event) {
            if ($event->level === 'warning' && $event->message === 'Sightengine moderation check failed') {
                $matches[] = $event;
            }
        }

        $this->assertCount(1, $matches);
        $this->assertSame($check, $matches[0]->context['check'] ?? null);
        $this->assertSame($reason, $matches[0]->context['reason'] ?? null);
        $this->assertStringNotContainsString('test-secret', (string) json_encode($matches[0]->context));
        $this->assertStringNotContainsString('test-secret', $matches[0]->message);
    }

    private function soleImageRequest(): Request
    {
        $recorded = Http::recorded(
            fn (Request $request): bool => str_contains($request->url(), '/1.0/check.json'),
        );

        $this->assertCount(1, $recorded);

        return $recorded->first()[0];
    }

    private function attachedMedia(Request $request): string
    {
        $contents = $this->mediaPart($request)['contents'] ?? null;
        $this->assertIsString($contents);

        return $contents;
    }

    private function attachedFilename(Request $request): string
    {
        $filename = $this->mediaPart($request)['filename'] ?? null;
        $this->assertIsString($filename);

        return $filename;
    }

    private function formField(Request $request, string $name): string
    {
        $part = collect($request->data())->first(
            fn (mixed $part): bool => is_array($part) && ($part['name'] ?? null) === $name,
        );
        $this->assertIsArray($part);
        $this->assertIsString($part['contents'] ?? null);

        return $part['contents'];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPart(Request $request): array
    {
        $part = collect($request->data())->first(
            fn (mixed $part): bool => is_array($part) && ($part['name'] ?? null) === 'media',
        );
        $this->assertIsArray($part);

        return $part;
    }

    private function patternedJpeg(string $filename, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 10) {
            for ($x = 0; $x < $width; $x += 10) {
                $color = imagecolorallocate(
                    $image,
                    ($x * 13 + $y * 7) % 256,
                    ($x * 3 + $y) % 256,
                    ($x + $y * 11) % 256,
                );
                imagefilledrectangle($image, $x, $y, min($width - 1, $x + 9), min($height - 1, $y + 9), $color);
            }
        }

        $path = $this->tempPath($filename);
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        return $path;
    }

    private function tinyJpeg(string $filename): string
    {
        $image = imagecreatetruecolor(20, 20);
        $color = imagecolorallocate($image, 20, 120, 40);
        imagefilledrectangle($image, 0, 0, 19, 19, $color);
        $path = $this->tempPath($filename);
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return $path;
    }

    private function noisyTransparentPng(string $filename): string
    {
        $width = 1600;
        $height = 1200;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                $alpha = ($x < 40 && $y < 40) ? 127 : 0;
                $inCenter = $x >= 500 && $x < 1100 && $y >= 350 && $y < 850;
                $color = $inCenter
                    ? imagecolorallocatealpha($image, 0, 230, 0, $alpha)
                    : imagecolorallocatealpha($image, random_int(0, 255), random_int(0, 255), random_int(0, 255), $alpha);
                imagefilledrectangle($image, $x, $y, min($width - 1, $x + 3), min($height - 1, $y + 3), $color);
            }
        }

        $path = $this->tempPath($filename);
        imagepng($image, $path, 1);
        imagedestroy($image);

        return $path;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    private function rgbAt(GdImage $image, int $x, int $y): array
    {
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return [
            'red' => $color['red'],
            'green' => $color['green'],
            'blue' => $color['blue'],
        ];
    }

    private function tempPath(string $filename): string
    {
        $directory = sys_get_temp_dir().'/moderation-'.uniqid('', true);
        mkdir($directory);

        return $directory.'/'.$filename;
    }

    private function deleteTempFile(string $path): void
    {
        @unlink($path);
        @rmdir(dirname($path));
    }

    private function jpegWithExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = 'II'
            ."\x2A\x00"
            ."\x08\x00\x00\x00"
            ."\x01\x00"
            ."\x12\x01"
            ."\x03\x00"
            ."\x01\x00\x00\x00"
            .pack('v', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";
        $exifHeader = "Exif\x00\x00".$tiff;

        return "\xFF\xD8"."\xFF\xE1".pack('n', strlen($exifHeader) + 2).$exifHeader.substr($jpeg, 2);
    }
}
