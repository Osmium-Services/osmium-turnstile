<?php

declare(strict_types=1);

namespace Tests\Services\Turnstile;

use Osmium\Services\Turnstile\Models\TurnstileOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TurnstileOutcome.php';

/**
 * What a Turnstile check came to, and what a form does about it.
 */
final class TurnstileOutcomeTest extends TestCase
{
    public function testNoTokenIsMissing(): void
    {
        $this->assertSame(TurnstileOutcome::MISSING, TurnstileOutcome::classify(token: '', response: ['success' => true]));
    }

    public function testNoAnswerFromCloudflareIsUnreachable(): void
    {
        $this->assertSame(TurnstileOutcome::UNREACHABLE, TurnstileOutcome::classify(token: 'abc', response: null));
    }

    public function testAnAnswerWithoutASuccessFieldIsUnreachable(): void
    {
        $this->assertSame(TurnstileOutcome::UNREACHABLE, TurnstileOutcome::classify(token: 'abc', response: ['oops' => 1]));
    }

    public function testSuccessIsPassed(): void
    {
        $this->assertSame(TurnstileOutcome::PASSED, TurnstileOutcome::classify(token: 'abc', response: ['success' => true]));
    }

    public function testRefusalIsFailed(): void
    {
        $this->assertSame(TurnstileOutcome::FAILED, TurnstileOutcome::classify(token: 'abc', response: ['success' => false, 'error-codes' => ['invalid-input-response']]));
    }

    public function testFormsStopOnlyOnFailedOrMissing(): void
    {
        $this->assertTrue(TurnstileOutcome::verdict(TurnstileOutcome::PASSED));
        $this->assertFalse(TurnstileOutcome::verdict(TurnstileOutcome::FAILED));
        $this->assertFalse(TurnstileOutcome::verdict(TurnstileOutcome::MISSING));
    }

    public function testAnUnreachableCloudflareFailsOpen(): void
    {
        $this->assertNull(TurnstileOutcome::verdict(TurnstileOutcome::UNREACHABLE));
    }
}
