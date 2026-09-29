<?php

namespace App\Actions;

use App\Enums\ModerationDecision;
use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerateContent
{
    private const TEXT_URL = 'https://api.sightengine.com/1.0/text/check.json';

    private const IMAGE_URL = 'https://api.sightengine.com/1.0/check.json';

    private const VIDEO_URL = 'https://api.sightengine.com/1.0/video/check-sync.json';

    /**
     * Category scores at or above this value are clearly unsafe.
     */
    private const REJECT_THRESHOLD = 0.80;

    /**
     * Category scores at or above this value, but below reject, need a person.
     */
    private const REVIEW_THRESHOLD = 0.40;

    /**
     * Explicit sexual display in photos. Kept higher so clothed people, families,
     * workers, and community events are not treated as adult content.
     */
    private const SUGGESTIVE_REJECT_THRESHOLD = 0.92;

    private const SUGGESTIVE_REVIEW_THRESHOLD = 0.60;

    /**
     * Longest side of the copy uploaded for image moderation.
     * Full-size phone photos time out on slow uplinks.
     */
    private const IMAGE_MAX_EDGE = 1280;

    /**
     * JPEG quality for that copy. Lowered further when the result is still large.
     */
    private const IMAGE_JPEG_QUALITY = 80;

    /**
     * Stay under Sightengine's practical upload size on a slow link.
     */
    private const IMAGE_MAX_BYTES = 280 * 1024;

    /**
     * One retry after this pause. Two 20s attempts plus this delay is the worst case.
     */
    private const IMAGE_RETRY_DELAY_MILLISECONDS = 200;

    /**
     * @var list<string>
     */
    private const MEDIA_MODELS = [
        'nudity-2.1',
        'offensive',
        'gore-2.0',
        'violence',
    ];

    /**
     * Sightengine ML text models. `categories` is only valid for rule-based
     * mode and must not be sent here.
     */
    private const TEXT_MODELS = 'general';

    /**
     * Nested payload keys that indicate harmful content. Suggestive clothing
     * classes and "a person is present" signals are intentionally omitted.
     *
     * @var list<string>
     */
    private const SCORED_KEYS = [
        'sexual_activity',
        'sexual_display',
        'erotica',
        'very_suggestive',
        'sexual',
        'discriminatory',
        'insulting',
        'violent',
        'toxic',
        'spam',
    ];

    /**
     * Object keys whose `prob` value should be scored.
     *
     * @var list<string>
     */
    private const SCORED_PROB_KEYS = [
        'offensive',
        'gore',
        'violence',
    ];

    /**
     * @var list<string>
     */
    private const IGNORED_BRANCHES = [
        'suggestive_classes',
        'context',
        'faces',
        'face',
        'text',
        'boxes',
        'info',
        'media',
        'request',
    ];

    /**
     * @param  list<string>  $imagePaths
     * @param  list<string>  $videoPaths
     */
    public function handle(string $text, array $imagePaths, array $videoPaths): ModerationDecision
    {
        $user = (string) config('services.sightengine.user');
        $secret = (string) config('services.sightengine.secret');

        if ($user === '' || $secret === '') {
            return ModerationDecision::Review;
        }

        try {
            $decision = ModerationDecision::Allow;

            $trimmedText = trim($text);

            if ($trimmedText !== '') {
                $decision = $this->worse(
                    $decision,
                    $this->fromResponse($this->checkText($trimmedText, $user, $secret), 'text'),
                );
            }

            if ($decision === ModerationDecision::Reject) {
                return $decision;
            }

            foreach ($imagePaths as $imagePath) {
                $decision = $this->worse(
                    $decision,
                    $this->fromMediaFile($imagePath, self::IMAGE_URL, $user, $secret, 20),
                );

                if ($decision === ModerationDecision::Reject) {
                    return $decision;
                }
            }

            foreach ($videoPaths as $videoPath) {
                $decision = $this->worse(
                    $decision,
                    $this->fromMediaFile($videoPath, self::VIDEO_URL, $user, $secret, 60),
                );

                if ($decision === ModerationDecision::Reject) {
                    return $decision;
                }
            }

            return $decision;
        } catch (Throwable $exception) {
            $this->logCheckFailure('moderation', $exception->getMessage());
            report($exception);

            return ModerationDecision::Review;
        }
    }

    private function checkText(string $text, string $user, string $secret): Response
    {
        return Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post(self::TEXT_URL, [
                'api_user' => $user,
                'api_secret' => $secret,
                'text' => $text,
                'mode' => 'ml',
                'lang' => 'en',
                'models' => self::TEXT_MODELS,
            ]);
    }

    private function fromMediaFile(string $path, string $url, string $user, string $secret, int $timeout): ModerationDecision
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ModerationDecision::Review;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return ModerationDecision::Review;
        }

        $isImage = $url === self::IMAGE_URL;
        $filename = basename($path);

        if ($isImage) {
            $downscaled = $this->downscaledImageContents($path, $contents);

            if ($downscaled !== null) {
                $contents = $downscaled;
                $filename = $this->jpegUploadFilename($filename);
            }
        }

        try {
            $response = $this->sendMedia($url, $contents, $filename, $user, $secret, $timeout, $isImage);
        } catch (ConnectionException $exception) {
            $this->logCheckFailure($isImage ? 'image' : 'video', $exception->getMessage());
            report($exception);

            return ModerationDecision::Review;
        }

        return $this->fromResponse($response, $isImage ? 'image' : 'video');
    }

    private function sendMedia(
        string $url,
        string $contents,
        string $filename,
        string $user,
        string $secret,
        int $timeout,
        bool $retryConnectionFailure,
    ): Response {
        $request = Http::connectTimeout(5)->timeout($timeout);

        if ($retryConnectionFailure) {
            $request = $request->retry(
                times: 2,
                sleepMilliseconds: self::IMAGE_RETRY_DELAY_MILLISECONDS,
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        return $request
            ->attach('media', $contents, $filename)
            ->post($url, [
                'api_user' => $user,
                'api_secret' => $secret,
                'models' => implode(',', self::MEDIA_MODELS),
            ]);
    }

    /**
     * Build a smaller JPEG for the moderation upload. Returns null when the
     * original should be sent instead. Never writes to $path.
     */
    private function downscaledImageContents(string $path, string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        try {
            $source = $this->applyExifOrientation($source, $path);

            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($source);
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($width < 1 || $height < 1) {
                return null;
            }

            $longestEdge = max($width, $height);

            if ($longestEdge <= self::IMAGE_MAX_EDGE && strlen($contents) <= self::IMAGE_MAX_BYTES) {
                return null;
            }

            $edge = self::IMAGE_MAX_EDGE;
            $quality = self::IMAGE_JPEG_QUALITY;
            $encoded = null;

            for ($pass = 0; $pass < 6; $pass++) {
                $jpeg = $this->encodeFlattenedJpeg($source, $width, $height, $edge, $quality);

                if ($jpeg === null) {
                    return null;
                }

                $encoded = $jpeg;

                if (strlen($jpeg) <= self::IMAGE_MAX_BYTES) {
                    break;
                }

                if ($quality > 60) {
                    $quality -= 10;

                    continue;
                }

                $edge = (int) floor($edge * 0.75);

                if ($edge < 480) {
                    break;
                }

                $quality = self::IMAGE_JPEG_QUALITY;
            }

            if ($encoded === null || strlen($encoded) >= strlen($contents)) {
                return null;
            }

            return $encoded;
        } catch (Throwable) {
            return null;
        } finally {
            imagedestroy($source);
        }
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = $this->exifOrientation($path);

        $adjusted = match ($orientation) {
            2 => $this->flipImage($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotateImage($image, 180),
            4 => $this->flipImage($image, IMG_FLIP_VERTICAL),
            5 => $this->rotateFlippedImage($image, IMG_FLIP_VERTICAL, -90),
            6 => $this->rotateImage($image, -90),
            7 => $this->rotateFlippedImage($image, IMG_FLIP_HORIZONTAL, -90),
            8 => $this->rotateImage($image, 90),
            default => $image,
        };

        if ($adjusted === null) {
            return $image;
        }

        if ($adjusted !== $image) {
            imagedestroy($image);
        }

        return $adjusted;
    }

    private function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        if (! is_array($exif)) {
            return 1;
        }

        $orientation = (int) ($exif['Orientation'] ?? 1);

        if ($orientation < 2 || $orientation > 8) {
            return 1;
        }

        return $orientation;
    }

    private function rotateImage(GdImage $image, float $angle): ?GdImage
    {
        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return null;
        }

        return $rotated;
    }

    private function rotateFlippedImage(GdImage $image, int $mode, float $angle): ?GdImage
    {
        if (! imageflip($image, $mode)) {
            return null;
        }

        return $this->rotateImage($image, $angle);
    }

    private function flipImage(GdImage $image, int $mode): ?GdImage
    {
        if (! imageflip($image, $mode)) {
            return null;
        }

        return $image;
    }

    private function encodeFlattenedJpeg(GdImage $source, int $width, int $height, int $maxEdge, int $quality): ?string
    {
        $scale = min(1.0, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'modimg');

        if ($temporaryPath === false) {
            imagedestroy($canvas);

            return null;
        }

        try {
            $white = imagecolorallocate($canvas, 255, 255, 255);

            if ($white === false) {
                return null;
            }

            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagealphablending($canvas, true);

            $copied = imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height,
            );

            if ($copied !== true) {
                return null;
            }

            if (! imagejpeg($canvas, $temporaryPath, $quality)) {
                return null;
            }

            $jpeg = file_get_contents($temporaryPath);

            if (! is_string($jpeg) || $jpeg === '') {
                return null;
            }

            return $jpeg;
        } finally {
            imagedestroy($canvas);
            @unlink($temporaryPath);
        }
    }

    private function jpegUploadFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if ($name === '') {
            return 'image.jpg';
        }

        return $name.'.jpg';
    }

    private function fromResponse(Response $response, string $check): ModerationDecision
    {
        if (! $response->successful()) {
            $this->logCheckFailure($check, 'HTTP '.$response->status());

            return ModerationDecision::Review;
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            $this->logCheckFailure($check, $this->unsuccessfulPayloadReason($payload));

            return ModerationDecision::Review;
        }

        return $this->fromScores($this->collectScores($payload));
    }

    private function unsuccessfulPayloadReason(mixed $payload): string
    {
        if (! is_array($payload)) {
            return 'invalid response';
        }

        $error = $payload['error'] ?? null;

        if (is_array($error)) {
            $message = $error['message'] ?? $error['type'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        $status = $payload['status'] ?? null;

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return 'unsuccessful response';
    }

    private function logCheckFailure(string $check, string $reason): void
    {
        Log::warning('Sightengine moderation check failed', [
            'check' => $check,
            'reason' => $this->withoutApiSecret($reason),
        ]);
    }

    private function withoutApiSecret(string $value): string
    {
        $secret = (string) config('services.sightengine.secret');

        if ($secret === '') {
            return $value;
        }

        return str_replace($secret, '[redacted]', $value);
    }

    /**
     * @param  list<array{key: string, score: float}>  $scores
     */
    private function fromScores(array $scores): ModerationDecision
    {
        $decision = ModerationDecision::Allow;

        foreach ($scores as $score) {
            $decision = $this->worse($decision, $this->fromScore($score['key'], $score['score']));
        }

        return $decision;
    }

    private function fromScore(string $key, float $score): ModerationDecision
    {
        if ($key === 'very_suggestive') {
            if ($score >= self::SUGGESTIVE_REJECT_THRESHOLD) {
                return ModerationDecision::Reject;
            }

            if ($score >= self::SUGGESTIVE_REVIEW_THRESHOLD) {
                return ModerationDecision::Review;
            }

            return ModerationDecision::Allow;
        }

        if ($score >= self::REJECT_THRESHOLD) {
            return ModerationDecision::Reject;
        }

        if ($score >= self::REVIEW_THRESHOLD) {
            return ModerationDecision::Review;
        }

        return ModerationDecision::Allow;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{key: string, score: float}>
     */
    private function collectScores(array $payload): array
    {
        $scores = [];

        $this->walkScores($payload, $scores);

        return $scores;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{key: string, score: float}>  $scores
     */
    private function walkScores(array $node, array &$scores): void
    {
        foreach ($node as $key => $value) {
            if (! is_string($key) || in_array($key, self::IGNORED_BRANCHES, true)) {
                continue;
            }

            if (is_array($value)) {
                if (
                    in_array($key, self::SCORED_PROB_KEYS, true)
                    && isset($value['prob'])
                    && is_numeric($value['prob'])
                ) {
                    $scores[] = [
                        'key' => $key,
                        'score' => (float) $value['prob'],
                    ];
                }

                $this->walkScores($value, $scores);

                continue;
            }

            if (! is_numeric($value) || ! in_array($key, self::SCORED_KEYS, true)) {
                continue;
            }

            $scores[] = [
                'key' => $key,
                'score' => (float) $value,
            ];
        }
    }

    private function worse(ModerationDecision $current, ModerationDecision $next): ModerationDecision
    {
        if ($current === ModerationDecision::Reject || $next === ModerationDecision::Reject) {
            return ModerationDecision::Reject;
        }

        if ($current === ModerationDecision::Review || $next === ModerationDecision::Review) {
            return ModerationDecision::Review;
        }

        return ModerationDecision::Allow;
    }
}
