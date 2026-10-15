<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Models;

/**
 * What a Turnstile check came to, and what a form should do about it. Pure: no network, no database.
 */
class TurnstileOutcome
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const MISSING = 'missing'; // The visitor sent no token: the widget did not load or the request is not from a browser
    public const UNREACHABLE = 'unreachable'; // Cloudflare could not be asked, or its answer made no sense

    /**
     * @param ?array<string, mixed> $response Cloudflare's decoded answer, null when there was none
     */
    public static function classify(string $token, ?array $response): string
    {
        $noToken = $token === '';
        if ($noToken) return self::MISSING;

        $noAnswer = $response === null || !\array_key_exists('success', $response);
        if ($noAnswer) return self::UNREACHABLE;

        return $response['success'] === true ? self::PASSED : self::FAILED;
    }

    /**
     * The answer to the form.verify hook: false stops the form. An unreachable Cloudflare fails open (null),
     * so an outage cannot lose a customer's review; the form's own honeypot, token and timer still apply.
     */
    public static function verdict(string $outcome): ?bool
    {
        return match ($outcome) {
            self::PASSED => true,
            self::FAILED, self::MISSING => false,
            default => null,
        };
    }
}
