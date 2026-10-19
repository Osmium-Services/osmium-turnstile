<?php

declare(strict_types=1);

namespace Tests\Services\Turnstile;

use Osmium\Services\Turnstile\Models\TurnstileWidget;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TurnstileWidget.php';

/**
 * The widget markup: the Turnstile div plus the script that holds the form's submit buttons until it has a token.
 */
final class TurnstileWidgetTest extends TestCase
{
    private function html(array $changes = []): string
    {
        $args = \array_merge(['siteKey' => '0xKEY', 'callbackId' => 'reviews1', 'nonce' => 'abc123'], $changes);

        return TurnstileWidget::html(siteKey: $args['siteKey'], callbackId: $args['callbackId'], nonce: $args['nonce']);
    }

    public function test_the_div_carries_the_site_key_and_its_callbacks(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('class="cf-turnstile" data-sitekey="0xKEY"', $html);
        $this->assertStringContainsString('data-callback="turnstileDonereviews1"', $html);
        $this->assertStringContainsString('data-expired-callback="turnstileWaitreviews1"', $html);
    }

    public function test_the_script_carries_the_csp_nonce(): void
    {
        $this->assertStringContainsString('<script nonce="abc123">', $this->html());
    }

    public function test_the_site_key_is_escaped(): void
    {
        $html = $this->html(['siteKey' => '"><script>x</script>']);

        $this->assertStringNotContainsString('<script>x', $html);
    }

    public function test_the_callback_id_cannot_break_out_of_its_name(): void
    {
        $html = $this->html(['callbackId' => 'a-b"c d']);

        $this->assertStringContainsString('turnstileDoneabcd', $html);
        $this->assertStringNotContainsString('a-b"c', $html);
    }

    public function test_the_buttons_are_released_after_a_few_seconds_whatever_happens(): void
    {
        $this->assertStringContainsString('}, ' . TurnstileWidget::RELEASE_AFTER_MS . ');', $this->html());
    }

    public function test_cloudflare_is_not_contacted_until_the_form_is_near_the_screen_or_touched(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('<script src=', $html, 'no script tag that loads at once');
        $this->assertStringContainsString("script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'", $html);
        $this->assertStringContainsString("rootMargin: '400px'", $html);
        $this->assertStringContainsString("form.addEventListener('focusin', load", $html);
        $this->assertStringContainsString("form.addEventListener('pointerdown', load", $html);
        $this->assertStringContainsString("form.addEventListener('touchstart', load", $html);
    }

    public function test_the_script_is_added_once_per_page_and_carries_the_nonce(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('window.osmiumTurnstileRequested', $html);
        $this->assertStringContainsString("script.nonce = 'abc123'", $html);
    }

    public function test_the_release_timer_starts_when_loading_starts_not_when_the_page_loads(): void
    {
        $html = $this->html();
        $timer = \strpos($html, '}, ' . TurnstileWidget::RELEASE_AFTER_MS . ');');

        $this->assertNotFalse($timer);
        $this->assertGreaterThan(\strpos($html, 'function load()'), $timer, 'the timer is inside load()');
        $this->assertLessThan(\strpos($html, 'if (document.readyState'), $timer, 'and not started at page load');
    }

    public function test_two_forms_get_their_own_callbacks(): void
    {
        $this->assertStringContainsString('turnstileDoneother2', $this->html(['callbackId' => 'other2']));
        $this->assertStringNotContainsString('turnstileDonereviews1', $this->html(['callbackId' => 'other2']));
    }
}
