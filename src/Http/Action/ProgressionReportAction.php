<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ReportFilter;
use MathDeck\Identity\Port\AccountStore;

/**
 * The only report that resolves pseudonyms back to people.
 *
 * The fact table holds keys; this reverses them for a caller the guard has already
 * established is a teacher. Every other report stays aggregate and never needs a
 * name at all.
 */
final readonly class ProgressionReportAction extends ReportAction
{
    public function __construct(ReportQueries $reports, private AccountStore $accounts)
    {
        parent::__construct($reports);
    }

    protected function data(ReportFilter $filter): array
    {
        $rows = $this->reports->progression($filter);
        $names = $this->accounts->displayNamesFor(array_column($rows, 'studentKey'));

        $named = array_map(
            static fn (array $row): array => [
                ...$row,
                'displayName' => $names[$row['studentKey']] ?? 'Unknown learner',
            ],
            $rows,
        );

        // The query orders by pseudonym, which is deterministic and meaningless to a
        // person. Sorting by name belongs here, in the one layer that knows them —
        // pseudonymising the store should not hand teachers a randomly ordered list.
        usort($named, static fn (array $a, array $b): int => $a['displayName'] <=> $b['displayName']);

        return $named;
    }
}
