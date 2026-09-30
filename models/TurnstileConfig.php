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
        $config = self::get();

        $tokenMissing = $token === '' || empty($config->secretKey ?? '');
        if ($tokenMissing) return false;

        $ch = \curl_init(self::SITEVERIFY_URL);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \http_build_query([
                'secret' => $config->secretKey,
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = \curl_exec($ch);
        $curlError = \curl_error($ch);
        if ($curlError) return false;

        $decoded = \json_decode($response, associative: true);

        return (bool) ($decoded['success'] ?? false);
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
