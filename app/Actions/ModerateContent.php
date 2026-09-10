<?php

namespace App\Actions;

use App\Enums\ModerationDecision;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
                    $this->fromResponse($this->checkText($trimmedText, $user, $secret)),
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

        try {
            $response = Http::connectTimeout(5)
                ->timeout($timeout)
                ->attach('media', $contents, basename($path))
                ->post($url, [
                    'api_user' => $user,
                    'api_secret' => $secret,
                    'models' => implode(',', self::MEDIA_MODELS),
                ]);
        } catch (ConnectionException $exception) {
            report($exception);

            return ModerationDecision::Review;
        }

        return $this->fromResponse($response);
    }

    private function fromResponse(Response $response): ModerationDecision
    {
        if (! $response->successful()) {
            return ModerationDecision::Review;
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            return ModerationDecision::Review;
        }

        return $this->fromScores($this->collectScores($payload));
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
