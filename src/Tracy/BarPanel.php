<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Tracy;

class BarPanel implements Tracy\IBarPanel
{
	public static bool $disabled = FALSE;

	public static int $showMaxLastQueries = 1000;

	private PhPgSql\Db\Connection $connection;

	private PhPgSql\Tracy\QueryDumper $queryDumper;

	private string $name;

	private float|NULL $longQueryTimeMs;

	private bool $detectRepeatingQueries;

	private float $totalTimeMs = 0;

	private int $count = 0;

	/** @var list<array{0: PhPgSql\Db\Query|string, 1: float|FALSE|NULL, 2: array<PhPgSql\Db\Row>|NULL, 3: array{file: string, line: int|NULL}|NULL}> */
	private array $queries = [];

	private int $longQueryCount = 0;

	/** @var array<string, int> */
	private array $queriesCount = [];

	/** @var array<string, int>|NULL */
	private array|NULL $repeatingQueries = NULL;

	/** @var list<PhPgSql\Db\Result> */
	private array $results = [];

	/** @var list<array{0: PhPgSql\Db\Query, 1: list<string>}>|NULL */
	private array|NULL $nonParsedColumnsQueries = NULL;

	private bool $disableLogQuery = FALSE;


	final public function __construct(
		PhPgSql\Db\Connection $connection,
		PhPgSql\Tracy\QueryDumper $queryDumper,
		string $name,
		bool $explain = FALSE,
		bool $notices = FALSE,
		float|NULL $longQueryTimeMs = NULL,
		bool $detectRepeatingQueries = FALSE,
		bool $detectNonParsedColumns = FALSE,
	)
	{
		$connection->addOnQuery(function (PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query, float|NULL $timeNs = NULL) use ($explain): void {
			$this->logQuery($query, $timeNs, $explain);
		});

		if ($notices) {
			$connection->addOnQuery(function (PhPgSql\Db\Connection $connection): void {
				$this->logNotices($connection);
			});
			$connection->addOnClose(function (PhPgSql\Db\Connection $connection): void {
				$this->logNotices($connection);
			});
		}

		if ($detectNonParsedColumns) {
			$connection->addOnResult(function (PhPgSql\Db\Connection $connection, PhPgSql\Db\Result $result): void {
				$this->results[] = $result;
			});
		}

		$this->connection = $connection;
		$this->queryDumper = $queryDumper;
		$this->name = $name;
		$this->longQueryTimeMs = $longQueryTimeMs;
		$this->detectRepeatingQueries = $detectRepeatingQueries;
	}


	public function logQuery(PhPgSql\Db\Query $query, float|NULL $timeNs, bool $explain): void
	{
		if (self::$disabled || $this->disableLogQuery) {
			return;
		}

		$this->count++;

		$timeMs = NULL;
		if ($timeNs !== NULL) {
			$timeMs = $timeNs / 1000000;
			$this->totalTimeMs += $timeMs;
		}

		if (($this->longQueryTimeMs !== NULL) && (($timeMs ?? 0) >= $this->longQueryTimeMs)) {
			$this->longQueryCount++;
		}

		if ($this->detectRepeatingQueries && (\preg_match('#^\s*(BEGIN|COMMIT|ROLLBACK|SET)#i', $query->getSql()) === 0)) {
			$this->queriesCount[$query->getSql()] = ($this->queriesCount[$query->getSql()] ?? 0) + 1;
		}

		$source = NULL;
		$trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($trace as $row) {
			$class = $row['class'] ?? '';
			$function = $row['function'];
			if (
				($class !== self::class)
				&& !\is_a($class, PhPgSql\Db\Events::class, TRUE)
				&& !(\is_a($class, PhPgSql\Db\Transaction::class, TRUE) && \in_array($function, ['begin', 'commit', 'rollback', 'savepoint', 'releaseSavepoint', 'rollbackToSavepoint'], TRUE))
				&& !(\is_a($class, PhPgSql\Db\Connection::class, TRUE) && \in_array($function, ['query', 'queryArgs', 'execute', 'asyncQuery', 'asyncQueryArgs', 'asyncExecute'], TRUE))
				&& !(\is_a($class, PhPgSql\Fluent\QueryExecute::class, TRUE) && \in_array($function, ['execute', 'fetch', 'fetchAll', 'fetchAssoc', 'fetchPairs', 'fetchSingle', 'fetchIterator'], TRUE))
				&& !static::backtraceContinueIterate($class, $function)
			) {
				break;
			}

			if (isset($row['file']) && \is_file($row['file'])) {
				$source = [
					'file' => $row['file'],
					'line' => $row['line'] ?? NULL,
				];
			}
		}

		$this->queries[] = [$query, $timeMs, $explain ? self::explain($query) : NULL, $source];
	}


	/**
	 * Can be over-written with custom logic to ignore concrete class and function in backtrace to show the most appropriate file/line in source code.
	 */
	protected static function backtraceContinueIterate(string $class, string $function): bool
	{
		return FALSE;
	}


	private function logNotices(PhPgSql\Db\Connection $connection): void
	{
		if (self::$disabled) {
			return;
		}

		$notices = $connection->getNotices();

		if ($notices !== []) {
			$this->queries[] = [
				\sprintf(
					'<pre class="dump"><strong style="color:gray">%s</strong></pre>',
					\implode('<br><br>', \array_map(static function (string $notice): string {
						return '<em>Notice:</em><br>' . \substr($notice, 9);
					}, \array_map('nl2br', $notices))),
				),
				FALSE,
				NULL,
				NULL,
			];
		}
	}


	/**
	 * @return list<PhPgSql\Db\Row>|NULL
	 */
	private function explain(PhPgSql\Db\Query $query): array|NULL
	{
		$sql = $query->getSql();

		if (\preg_match('#\s*\(?\s*SELECT\s#iA', $sql) === 0) {
			return NULL;
		}

		$explainQuery = new PhPgSql\Db\Sql\Query('EXPLAIN ' . $sql, $query->getParams());

		try {
			$this->disableLogQuery = TRUE;
			$explain = $this->connection->query($explainQuery)->fetchAll();
		} catch (PhPgSql\Db\Exceptions\QueryException) {
			$explain = NULL;
		} finally {
			$this->disableLogQuery = FALSE;
		}

		return $explain;
	}


	public function getTab(): string
	{
		$name = $this->name;
		$count = $this->count;
		$totalTimeMs = $this->totalTimeMs;

		$hasLongQuery = $this->longQueryCount > 0;
		$hasRepeatingQueries = \count($this->getRepeatingQueries()) > 0;
		$hasNonParsedColumns = \count($this->getNonParsedColumnsQueries()) > 0;

		\ob_start(static function (): void {
		});

		require __DIR__ . '/templates/BarPanel.tab.phtml';

		$data = \ob_get_clean();

		return $data === FALSE ? '' : $data;
	}


	public function getPanel(): string
	{
		if ($this->count === 0) {
			return '';
		}

		$name = $this->name;
		$count = $this->count;
		$totalTimeMs = $this->totalTimeMs;
		$queries = \array_slice($this->queries, -1 * self::$showMaxLastQueries);

		$longQueryTimeMs = $this->longQueryTimeMs;

		$longQueryCount = $this->longQueryCount;
		$repeatingQueries = $this->getRepeatingQueries();
		$nonParsedColumnsQueries = $this->getNonParsedColumnsQueries();

		$queryDump = function (string $sql, array $parameters = []): string {
			return \sprintf('<pre class="dump">%s</pre>', $this->queryDumper->dump($sql, $parameters));
		};

		$paramsDump = static function (array $parameters): string {
			return Helper::dumpParameters($parameters);
		};

		\ob_start(static function (): void {
		});

		require __DIR__ . '/templates/BarPanel.panel.phtml';

		$data = \ob_get_clean();

		return $data === FALSE ? '' : $data;
	}


	/**
	 * @return array<string, int>
	 */
	private function getRepeatingQueries(): array
	{
		if ($this->repeatingQueries === NULL) {
			$this->repeatingQueries = \array_filter($this->queriesCount, static function (int $count): bool {
				return $count > 1;
			});
			\arsort($this->repeatingQueries);
		}

		return $this->repeatingQueries;
	}


	/**
	 * @return list<array{0: PhPgSql\Db\Query, list<string>}>
	 */
	private function getNonParsedColumnsQueries(): array
	{
		if ($this->nonParsedColumnsQueries === NULL) {
			$this->nonParsedColumnsQueries = [];

			foreach ($this->results as $result) {
				$nonParsedColumns = \array_filter($result->getParsedColumns() ?? [], static function (bool $isUsed): bool {
					return !$isUsed;
				});

				if ($nonParsedColumns !== []) {
					$this->nonParsedColumnsQueries[] = [$result->getQuery(), \array_keys($nonParsedColumns)];
				}
			}
		}

		return $this->nonParsedColumnsQueries;
	}


	public static function initialize(
		PhPgSql\Db\Connection $connection,
		PhPgSql\Tracy\QueryDumper $queryDumper,
		string $name,
		bool $explain,
		bool $notices,
		float|NULL $longQueryTimeMs = NULL,
		bool $detectRepeatingQueries = FALSE,
		bool $detectNonParsedColumns = FALSE,
	): self
	{
		$panel = new static($connection, $queryDumper, $name, $explain, $notices, $longQueryTimeMs, $detectRepeatingQueries, $detectNonParsedColumns);
		Tracy\Debugger::getBar()->addPanel($panel);
		return $panel;
	}

}
