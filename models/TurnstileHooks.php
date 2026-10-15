<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Models;

/**
 * The handlers for the core form hooks (form.verify, form.widget). A form that fires those hooks is protected
 * while this service is enabled, and never has to name it.
 */
class TurnstileHooks
{
    /**
     * form.verify: true when Cloudflare passed the visitor, false when it did not or sent no token, null when this
     * service is off or Cloudflare could not be asked (the form then relies on its own checks).
     *
     * @param array{form: string, post: array, dataSource: object} $payload
     */
    public static function verify(array $payload): ?bool
    {
        $off = !(TurnstileConfig::get()->enabled ?? false);
        if ($off) return null;

        $outcome = TurnstileConfig::check((string) ($payload['post']['cf-turnstile-response'] ?? ''));
        self::record(dataSource: $payload['dataSource'], form: (string) ($payload['form'] ?? ''), outcome: $outcome);

        return TurnstileOutcome::verdict($outcome);
    }

    /**
     * form.widget: the div Cloudflare's script turns into the widget, or null when this service is off.
     *
     * @param array{form: string} $payload
     */
    public static function widget(array $payload): ?string
    {
        $config = TurnstileConfig::get();
        $notUsable = !($config->enabled ?? false) || empty($config->siteKey ?? '');
        if ($notUsable) return null;

        return '<div class="cf-turnstile" data-sitekey="' . \htmlspecialchars((string) $config->siteKey, ENT_QUOTES) . '"></div>';
    }

    /** Counting must never stop a visitor's form */
    private static function record(object $dataSource, string $form, string $outcome): void
    {
        try {
            (new TurnstileStats($dataSource))->record(form: \substr($form, 0, 40), outcome: $outcome);
        } catch (\Throwable $e) {
            \error_log('Turnstile stats failed: ' . $e->getMessage());
        }
    }
}
