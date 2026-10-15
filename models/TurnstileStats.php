<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * A running count of Turnstile checks per day, form and outcome, so the admin can see protection working.
 */
class TurnstileStats extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'turnstile_checks');
    }

    public function record(string $form, string $outcome): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('check-record.sql'), bindings: [':form' => $form, ':outcome' => $outcome])->execute();
    }

    /** @return array<int, array{form: string, outcome: string, total: string}> */
    public function summary(int $days): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('check-summary.sql'), bindings: [':days' => $days])->resultset();
    }

    public function purgeOld(): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('check-purge.sql'), bindings: [])->execute();
    }
}
