<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AccountManagementTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
        $this->app->authAs('miss-lee');
        $this->app->authAs('alice');
    }

    /**
     * The whole point: an account made through the API can be signed in with.
     * Everything else here is detail around this.
     */
    public function testAChildAddedByATeacherCanSignIn(): void
    {
        $response = $this->createStudent('ada', 'Ada');
        $created = $this->app->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('student', $created['role']);
        self::assertSame('/v1/accounts/ada', $this->createdLocation);

        $signIn = $this->app->request('POST', '/v1/tokens', [
            'playerId' => 'ada',
            'passcode' => $created['passcode'],
        ]);

        self::assertSame(200, $signIn->getStatusCode());
        self::assertSame('student', $this->app->json($signIn)['role']);
    }

    public function testAGeneratedPasscodeIsSomethingAChildCouldType(): void
    {
        $created = $this->app->json($this->createStudent('ada', 'Ada'));

        self::assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $created['passcode']);
    }

    /** A hash is a credential even hashed; a pseudonym beside a name undoes itself. */
    public function testNoResponseCarriesAHashOrAPseudonym(): void
    {
        $created = $this->createStudent('ada', 'Ada');
        $list = $this->app->request('GET', '/v1/accounts', null, $this->app->authAs('miss-lee'));

        foreach ([$created, $list] as $response) {
            $body = $this->app->bodyOf($response);

            self::assertStringNotContainsString('passwordHash', $body);
            self::assertStringNotContainsString('analyticsKey', $body);
            self::assertStringNotContainsString('$argon', $body);
            self::assertStringNotContainsString('$2y$', $body);
        }
    }

    /**
     * No account may grant its own level of access to another, so a teacher creates
     * students and nothing else. Teachers come from bin/create-account.
     */
    public function testATeacherCannotCreateAnotherTeacher(): void
    {
        $created = $this->app->json($this->app->request('POST', '/v1/accounts', [
            'playerId' => 'mr-adeyemi',
            'displayName' => 'Mr Adeyemi',
            'role' => 'teacher',
        ], $this->app->authAs('miss-lee'), validateRequest: false));

        self::assertSame('student', $created['role'], 'A role in the body is simply not read.');
    }

    public function testAStudentCannotAddAccountsOrSeeTheClassList(): void
    {
        self::assertSame(403, $this->app->request('POST', '/v1/accounts', [
            'playerId' => 'ada',
            'displayName' => 'Ada',
        ], $this->app->authAs('alice'))->getStatusCode());

        self::assertSame(
            403,
            $this->app->request('GET', '/v1/accounts', null, $this->app->authAs('alice'))->getStatusCode(),
        );
    }

    public function testATakenNameIsAConflict(): void
    {
        $this->createStudent('ada', 'Ada');

        $response = $this->createStudent('ada', 'Ada Lovelace');

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('already signs in', $this->app->json($response)['detail']);
    }

    public function testAnUnusableNameIsRefused(): void
    {
        $response = $this->createStudent('Ada Lovelace', 'Ada');

        self::assertSame(400, $response->getStatusCode());
    }

    public function testTheClassListShowsEveryone(): void
    {
        $this->createStudent('ada', 'Ada');

        $body = $this->app->json($this->app->request('GET', '/v1/accounts', null, $this->app->authAs('miss-lee')));

        self::assertContains('ada', array_column($body['accounts'], 'playerId'));
        self::assertContains('miss-lee', array_column($body['accounts'], 'playerId'));
    }

    public function testAResetIssuesANewPasscodeAndRetiresTheOld(): void
    {
        $old = $this->app->json($this->createStudent('ada', 'Ada'))['passcode'];

        $reset = $this->app->json($this->resetPasscode('ada'));

        self::assertNotSame($old, $reset['passcode']);
        self::assertSame(401, $this->signIn('ada', $old)->getStatusCode(), 'The old one is gone.');
        self::assertSame(200, $this->signIn('ada', $reset['passcode'])->getStatusCode());
    }

    /**
     * A child who has locked themselves out guessing is exactly who a reset is for,
     * so it would be perverse for the throttle to keep them out afterwards.
     */
    public function testAResetAlsoClearsTheLockout(): void
    {
        $this->createStudent('ada', 'Ada');

        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $this->signIn('ada', 'guessing');
        }

        self::assertSame(429, $this->signIn('ada', 'guessing')->getStatusCode());

        $reset = $this->app->json($this->resetPasscode('ada'));

        self::assertSame(200, $this->signIn('ada', $reset['passcode'])->getStatusCode());
    }

    /** A teacher may choose the passcode, for one they can read out to the class. */
    public function testATeacherCanSupplyThePasscodeOnAReset(): void
    {
        $this->createStudent('ada', 'Ada');

        $reset = $this->app->json($this->resetPasscode('ada', 'read-this-out-loud'));

        self::assertSame('read-this-out-loud', $reset['passcode']);
        self::assertSame(200, $this->signIn('ada', 'read-this-out-loud')->getStatusCode());
    }

    public function testResettingSomebodyWhoDoesNotExistIs404(): void
    {
        self::assertSame(404, $this->resetPasscode('nobody')->getStatusCode());
    }

    public function testAStudentCannotResetAnotherChildsPasscode(): void
    {
        $this->createStudent('ada', 'Ada');

        self::assertSame(403, $this->app->request(
            'POST',
            '/v1/accounts/ada/passcode',
            [],
            $this->app->authAs('alice'),
        )->getStatusCode());
    }

    public function testChangingYourOwnPasscode(): void
    {
        $created = $this->app->json($this->createStudent('ada', 'Ada'));

        $changed = $this->app->request('PUT', '/v1/accounts/me/passcode', [
            'currentPasscode' => $created['passcode'],
            'newPasscode' => 'my-own-passcode',
        ], $this->tokenFor('ada', $created['passcode']));

        self::assertSame(200, $changed->getStatusCode());
        self::assertSame(401, $this->signIn('ada', $created['passcode'])->getStatusCode());
        self::assertSame(200, $this->signIn('ada', 'my-own-passcode')->getStatusCode());
    }

    public function testTheWrongCurrentPasscodeChangesNothing(): void
    {
        $created = $this->app->json($this->createStudent('ada', 'Ada'));
        $headers = $this->tokenFor('ada', $created['passcode']);

        $response = $this->app->request('PUT', '/v1/accounts/me/passcode', [
            'currentPasscode' => 'not-the-passcode',
            'newPasscode' => 'my-own-passcode',
        ], $headers);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(200, $this->signIn('ada', $created['passcode'])->getStatusCode());
    }

    /** This endpoint checks a credential, so it is throttled like signing in. */
    public function testGuessingTheCurrentPasscodeHereIsThrottledToo(): void
    {
        $created = $this->app->json($this->createStudent('ada', 'Ada'));
        $headers = $this->tokenFor('ada', $created['passcode']);

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $this->app->request('PUT', '/v1/accounts/me/passcode', [
                'currentPasscode' => 'guess',
                'newPasscode' => 'my-own-passcode',
            ], $headers);
        }

        $response = $this->app->request('PUT', '/v1/accounts/me/passcode', [
            'currentPasscode' => 'guess',
            'newPasscode' => 'my-own-passcode',
        ], $headers);

        self::assertSame(429, $response->getStatusCode());
    }

    private string $createdLocation = '';

    private function createStudent(string $playerId, string $displayName): ResponseInterface
    {
        $response = $this->app->request('POST', '/v1/accounts', [
            'playerId' => $playerId,
            'displayName' => $displayName,
        ], $this->app->authAs('miss-lee'));

        $this->createdLocation = $response->getHeaderLine('Location');

        return $response;
    }

    private function resetPasscode(string $playerId, ?string $passcode = null): ResponseInterface
    {
        return $this->app->request(
            'POST',
            sprintf('/v1/accounts/%s/passcode', $playerId),
            $passcode === null ? [] : ['passcode' => $passcode],
            $this->app->authAs('miss-lee'),
        );
    }

    private function signIn(string $playerId, string $passcode): ResponseInterface
    {
        return $this->app->request('POST', '/v1/tokens', ['playerId' => $playerId, 'passcode' => $passcode]);
    }

    /** @return array<string, string> */
    private function tokenFor(string $playerId, string $passcode): array
    {
        $token = $this->app->json($this->signIn($playerId, $passcode))['token'];

        return ['Authorization' => 'Bearer ' . $token];
    }
}
