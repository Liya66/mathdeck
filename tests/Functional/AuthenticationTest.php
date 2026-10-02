<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class AuthenticationTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
        $this->app->authAs('alice');
        $this->app->authAs('miss-lee');
    }

    public function testSigningInReturnsASignedToken(): void
    {
        $response = $this->app->request('POST', '/v1/tokens', [
            'playerId' => 'alice',
            'passcode' => $this->app->passcodeFor('alice'),
        ]);
        $body = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('alice', $body['playerId']);
        self::assertSame('student', $body['role']);
        self::assertStringStartsWith('v1.', $body['token']);
    }

    public function testTheIssuedTokenWorksOnAProtectedRoute(): void
    {
        $token = $this->app->json($this->app->request('POST', '/v1/tokens', [
            'playerId' => 'alice',
            'passcode' => $this->app->passcodeFor('alice'),
        ]))['token'];

        $response = $this->app->request('GET', '/v1/decks', null, ['Authorization' => 'Bearer ' . $token]);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * The two failure modes must be indistinguishable, or the endpoint becomes a
     * way to find out which children have accounts.
     */
    public function testAWrongPasscodeAndAnUnknownAccountLookTheSame(): void
    {
        $wrong = $this->app->request('POST', '/v1/tokens', ['playerId' => 'alice', 'passcode' => 'nope']);
        $unknown = $this->app->request('POST', '/v1/tokens', ['playerId' => 'nobody', 'passcode' => 'nope']);

        self::assertSame(401, $wrong->getStatusCode());
        self::assertSame(401, $unknown->getStatusCode());
        self::assertSame($this->app->json($wrong)['detail'], $this->app->json($unknown)['detail']);
    }

    public function testTypingYourNameIsNoLongerEnough(): void
    {
        // Exactly what worked in phases 4 through 7.
        $response = $this->app->request(
            'GET',
            '/v1/decks',
            null,
            ['Authorization' => 'Bearer alice'],
            validateRequest: false,
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testATamperedTokenIsRefused(): void
    {
        $header = $this->app->authAs('alice')['Authorization'];
        [$version, $payload, $signature] = explode('.', substr($header, 7));

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), associative: true);
        $claims['role'] = 'teacher';
        $forged = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $response = $this->app->request(
            'GET',
            '/v1/reports/overview',
            null,
            ['Authorization' => sprintf('Bearer %s.%s.%s', $version, $forged, $signature)],
            validateRequest: false,
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $header = $this->app->authAs('alice');

        $this->app->clock->advanceMs(13 * 3600 * 1000);

        $response = $this->app->request('GET', '/v1/decks', null, $header);

        self::assertSame(401, $response->getStatusCode());
    }

    /** The role is a signed claim, not something a caller asserts about themselves. */
    public function testAStudentCannotReachClassReportsAndATeacherCan(): void
    {
        self::assertSame(
            403,
            $this->app->request('GET', '/v1/reports/overview', null, $this->app->authAs('alice'))->getStatusCode(),
        );

        self::assertSame(
            200,
            $this->app->request('GET', '/v1/reports/overview', null, $this->app->authAs('miss-lee'))->getStatusCode(),
        );
    }

    public function testSigningInNeedsBothFields(): void
    {
        $response = $this->app->request(
            'POST',
            '/v1/tokens',
            ['playerId' => 'alice'],
            [],
            validateRequest: false,
        );

        self::assertSame(400, $response->getStatusCode());
    }
}
