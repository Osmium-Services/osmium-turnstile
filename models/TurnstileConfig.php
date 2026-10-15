<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Models;

/**
 * Cloudflare Turnstile configuration + server-side token verification.
 *
 * File-based config (app/config/services/turnstile.json.php), matching the
 * Xero/Stripe/PayPal/Analytics convention. verify() is the callable a form
 * handler uses directly - this service has no admin-facing "protect this
 * form" toggle, a form either calls verify() or it doesn't.
 */
class TurnstileConfig
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private static ?object $config = null;
    private static string $configPath = 'app/config/services/turnstile.json.php';

    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configFile = self::$configPath;

        $configExists = \file_exists($configFile);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents($configFile);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $json = \substr(string: $content, offset: $jsonStart);
        $decoded = \json_decode($json);

        self::$config = $decoded->turnstile ?? self::defaults();

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * Verify a submitted token (the "cf-turnstile-response" POST field)
     * against Cloudflare's siteverify endpoint.
     */
    public static function verify(string $token): bool
    {
        return self::check($token) === TurnstileOutcome::PASSED;
    }

    /**
     * Ask Cloudflare about a token and say what came of it (a TurnstileOutcome constant). Cloudflare's own
     * error codes (a wrong secret key, a hostname missing from the widget) go to the error log.
     */
    public static function check(string $token): string
    {
        $config = self::get();

        $noSecret = empty($config->secretKey ?? '');
        if ($noSecret) return TurnstileOutcome::classify(token: $token, response: null);

        $noToken = $token === '';
        if ($noToken) return TurnstileOutcome::MISSING;

        $response = self::askCloudflare(secretKey: (string) $config->secretKey, token: $token);

        $outcome = TurnstileOutcome::classify(token: $token, response: $response);
        $bad = $outcome !== TurnstileOutcome::PASSED;
        if ($bad) \error_log('Turnstile check ' . $outcome . ': ' . \json_encode($response['error-codes'] ?? 'no answer from Cloudflare'));

        return $outcome;
    }

    /** @return ?array<string, mixed> Cloudflare's decoded answer, or null when it could not be asked */
    private static function askCloudflare(string $secretKey, string $token): ?array
    {
        $ch = \curl_init(self::SITEVERIFY_URL);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \http_build_query([
                'secret' => $secretKey,
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = \curl_exec($ch);
        $curlError = \curl_error($ch);
        if ($curlError || !\is_string($body)) return null;

        $decoded = \json_decode($body, associative: true);

        return \is_array($decoded) ? $decoded : null;
    }

    private static function defaults(): object
    {
        return (object) [
            'enabled' => false,
            'siteKey' => '',
            'secretKey' => '',
            'mode' => 'managed', // managed | non-interactive | invisible
        ];
    }
}
