<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class SignInThrottleEndpointTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
        $this->app->authAs('alice');
    }

    public function testGuessingIsCutOffWithA429AndARetryAfter(): void
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::assertSame(401, $this->signIn('alice', 'guess')->getStatusCode());
        }

        $response = $this->signIn('alice', 'guess');

        // Not a 401: the credentials were never examined on this one.
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('300', $response->getHeaderLine('Retry-After'));
        self::assertSame('Too many attempts', $this->app->json($response)['title']);
    }

    public function testEvenTheRightPasscodeIsRefusedWhileThrottled(): void
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $this->signIn('alice', 'guess');
        }

        self::assertSame(429, $this->signIn('alice', $this->app->passcodeFor('alice'))->getStatusCode());
    }

    public function testASuccessfulSignInClearsTheAccountsBudget(): void
    {
        $this->signIn('alice', 'guess');
        self::assertSame(200, $this->signIn('alice', $this->app->passcodeFor('alice'))->getStatusCode());

        // A full budget again.
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::assertSame(401, $this->signIn('alice', 'guess')->getStatusCode());
        }
    }

    public function testWaitingOutTheWindowLetsYouTryAgain(): void
    {
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $this->signIn('alice', 'guess');
        }

        self::assertSame(429, $this->signIn('alice', 'guess')->getStatusCode());

        $this->app->clock->advanceMs(301_000);

        self::assertSame(200, $this->signIn('alice', $this->app->passcodeFor('alice'))->getStatusCode());
    }

    /** Throttling must not become a way to tell which accounts exist. */
    public function testAnUnknownAccountIsThrottledTheSameWay(): void
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::assertSame(401, $this->signIn('nobody', 'guess')->getStatusCode());
        }

        self::assertSame(429, $this->signIn('nobody', 'guess')->getStatusCode());
    }

    private function signIn(string $playerId, string $passcode): \Psr\Http\Message\ResponseInterface
    {
        return $this->app->request('POST', '/v1/tokens', [
            'playerId' => $playerId,
            'passcode' => $passcode,
        ]);
    }
}
