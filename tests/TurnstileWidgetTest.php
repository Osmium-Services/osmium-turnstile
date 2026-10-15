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

    public function test_two_forms_get_their_own_callbacks(): void
    {
        $this->assertStringContainsString('turnstileDoneother2', $this->html(['callbackId' => 'other2']));
        $this->assertStringNotContainsString('turnstileDonereviews1', $this->html(['callbackId' => 'other2']));
    }
}
