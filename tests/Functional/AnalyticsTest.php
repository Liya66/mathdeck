<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\FixedMatchIdentityFactory;
use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * The claim phase 7 rests on: the event log *is* the telemetry.
 *
 * Nothing in this test instruments gameplay. A match is played over HTTP exactly as
 * a child would play it, the projection worker runs, and the teacher reports are
 * read back — all from the same stream the engine already emitted.
 */
final class AnalyticsTest extends TestCase
{
    private const SOLVABLE_SEED = 6;

    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp(identity: new FixedMatchIdentityFactory(self::SOLVABLE_SEED));

        $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'bob'],
        ], $this->app->authAs('alice') + ['Idempotency-Key' => 'create-1']);

        // Time passes while a learner thinks. Latency is measured server-side from
        // the moment the turn opened, so this is what the report will see.
        $this->app->clock->advanceMs(3000);
        $this->play('alice', $this->solvingCardIds('alice'), 'cmd-1');

        $this->app->clock->advanceMs(7000);
        $this->play('bob', $this->threeNumbersInARow('bob'), 'cmd-2');
    }

    public function testReportsAreEmptyUntilTheProjectionRuns(): void
    {
        // Reports read projections, never the event log. Before the worker has run
        // there is genuinely nothing to report, and that is the correct answer.
        $overview = $this->report('overview');

        self::assertSame(0, $overview['attempts']);
        self::assertNull($overview['medianLatencyMs']);
    }

    public function testPlayedMatchesBecomeTeacherReports(): void
    {
        $projected = $this->app->project();

        self::assertSame(2, $projected['attempts']);

        $overview = $this->report('overview');

        self::assertSame(2, $overview['attempts'], 'Both tries counted, not just the successful one.');
        self::assertSame(1, $overview['solved']);
        self::assertSame(0.5, $overview['accuracy']);
        self::assertSame(2, $overview['students']);
        self::assertSame(1, $overview['matches']);
        self::assertGreaterThan(0, $overview['totalScore']);
    }

    /**
     * The teacher sees names; the store holds pseudonyms. Progression is the only
     * report that crosses back, and only for a caller the guard has cleared.
     */
    public function testProgressionSeparatesTheTwoLearnersByName(): void
    {
        $this->app->project();

        $rows = array_column($this->report('progression'), null, 'displayName');

        self::assertSame(['Alice', 'Bob'], array_keys($rows));
        self::assertSame(1, $rows['Alice']['solved']);
        self::assertSame(3000, $rows['Alice']['medianLatencyMs']);
        self::assertSame(0, $rows['Bob']['solved']);
        self::assertSame(7000, $rows['Bob']['medianLatencyMs']);
    }

    /**
     * What the analytics store actually holds. If this ever fails, the fact table
     * has started keeping names and the pseudonymisation is decorative.
     */
    public function testTheFactStoreHoldsNoNames(): void
    {
        $this->app->project();

        $rows = $this->report('progression');
        $keys = array_column($rows, 'studentKey');

        self::assertCount(2, $keys);

        foreach (['alice', 'bob', 'Alice', 'Bob'] as $name) {
            foreach ($keys as $key) {
                self::assertStringNotContainsString($name, $key);
            }
        }

        self::assertSame(
            [$this->app->accounts->analyticsKeyFor('alice'), $this->app->accounts->analyticsKeyFor('bob')],
            $keys,
        );
    }

    /**
     * The report the reason-code taxonomy from phase 2 was designed for.
     */
    public function testMistakesClusterByMisconception(): void
    {
        $this->app->project();

        $clusters = $this->report('errors');

        self::assertCount(1, $clusters);
        self::assertSame('MALFORMED', $clusters[0]['reason']);
        self::assertSame(1, $clusters[0]['count']);
        self::assertSame(1.0, $clusters[0]['share']);
        self::assertSame(1, $clusters[0]['students']);
    }

    public function testLatencyIsSplitByOutcome(): void
    {
        $this->app->project();

        $buckets = array_column($this->report('latency'), null, 'bucket');

        self::assertSame(3000, $buckets['solved']['medianLatencyMs']);
        self::assertSame(7000, $buckets['MALFORMED']['medianLatencyMs']);
    }

    public function testRunningTheWorkerTwiceDoesNotDoubleTheNumbers(): void
    {
        $this->app->project();
        $second = $this->app->project();

        self::assertSame(0, $second['attempts'], 'The cursor remembered where it got to.');
        self::assertSame(2, $this->report('overview')['attempts']);
    }

    public function testFilteringByDeckAndStudent(): void
    {
        $this->app->project();

        self::assertSame(2, $this->report('overview', 'deckVersionId=starter%401')['attempts']);
        self::assertSame(0, $this->report('overview', 'deckVersionId=other%401')['attempts']);
        // Filtering is by pseudonym, because that is what the reports hold.
        $aliceKey = $this->app->accounts->analyticsKeyFor('alice');
        self::assertNotNull($aliceKey);
        self::assertSame(1, $this->report('overview', 'students=' . $aliceKey)['attempts']);
    }

    /**
     * Class data is the one thing here that exposes one person to another.
     */
    public function testALearnerCannotReadClassReports(): void
    {
        $response = $this->app->request('GET', '/v1/reports/overview', null, $this->app->authAs('alice'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Class reports are for teachers.', $this->app->json($response)['detail']);
    }

    public function testABadDateIsRejectedRatherThanIgnored(): void
    {
        $response = $this->app->request(
            'GET',
            '/v1/reports/overview?from=whenever',
            null,
            $this->app->authAs('miss-lee'),
            validateRequest: false,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function report(string $name, string $query = ''): array
    {
        $path = sprintf('/v1/reports/%s%s', $name, $query === '' ? '' : '?' . $query);

        return $this->app->json($this->app->request('GET', $path, null, $this->app->authAs('miss-lee')))['data'];
    }

    /** @param list<string> $cardIds */
    private function play(string $playerId, array $cardIds, string $commandId): void
    {
        $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'play_cards', 'cardIds' => $cardIds],
            $this->app->authAs($playerId) + ['Idempotency-Key' => $commandId],
        );
    }

    /** @return list<string> */
    private function solvingCardIds(string $playerId): array
    {
        $view = $this->viewFor($playerId);
        $operands = array_values(array_filter($view['you']['hand'], static fn (array $c): bool => $c['kind'] === 'operand'));
        $operators = array_values(array_filter($view['you']['hand'], static fn (array $c): bool => $c['kind'] === 'operator'));

        foreach ($operands as $left) {
            foreach ($operands as $right) {
                if ($left['id'] === $right['id']) {
                    continue;
                }

                foreach ($operators as $operator) {
                    if ($operator['operator'] === '+' && $left['value'] + $right['value'] === $view['target']) {
                        return [$left['id'], $operator['id'], $right['id']];
                    }
                }
            }
        }

        self::fail('No solving play in the opening hand; the pinned seed changed.');
    }

    /** @return list<string> */
    private function threeNumbersInARow(string $playerId): array
    {
        $operands = array_values(array_filter(
            $this->viewFor($playerId)['you']['hand'],
            static fn (array $card): bool => $card['kind'] === 'operand',
        ));

        return [$operands[0]['id'], $operands[1]['id'], $operands[2]['id']];
    }

    /** @return array<string, mixed> */
    private function viewFor(string $playerId): array
    {
        return $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs($playerId)),
        );
    }

}
