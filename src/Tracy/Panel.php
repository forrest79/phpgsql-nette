<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Tracy;

class Panel implements Tracy\IBarPanel
{
	/** @var bool */
	public static $disabled = FALSE;

	/** @var PhPgSql\Db\Connection */
	private $connection;

	/** @var string */
	private $name;

	/** @var float|NULL in seconds */
	private $longQueryTime;

	/** @var bool */
	private $detectRepeatingQueries;

	/** @var float in seconds */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array<array{0: PhPgSql\Db\Query|string, 1: float|FALSE|NULL, 2: array<PhPgSql\Db\Row>|NULL}> */
	private $queries = [];

	/** @var int */
	private $longQueryCount = 0;

	/** @var array<string, int> */
	private $queriesCount = [];

	/** @var array<string, int>|NULL */
	private $repeatingQueries = NULL;

	/** @var array<PhPgSql\Db\Result> */
	private $results = [];

	/** @var array<array{0: PhPgSql\Db\Query, 1: array<string>}>|NULL */
	private $nonParsedColumnsQueries = NULL;

	/** @var bool */
	private $disableLogQuery = FALSE;


	private function __construct(
		PhPgSql\Db\Connection $connection,
		string $name,
		bool $explain = FALSE,
		bool $notices = FALSE,
		?float $longQueryTime = NULL,
		bool $detectRepeatingQueries = FALSE,
		bool $detectNonParsedColumns = FALSE
	)
	{
		$connection->addOnQuery(function (PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query, ?float $time = NULL) use ($explain): void {
			$this->logQuery($query, $time, $explain);
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
		$this->name = $name;
		$this->longQueryTime = $longQueryTime;
		$this->detectRepeatingQueries = $detectRepeatingQueries;
	}


	public function logQuery(PhPgSql\Db\Query $query, ?float $time, bool $explain): void
	{
		if (self::$disabled || $this->disableLogQuery) {
			return;
		}

		$this->count++;

		if ($time !== NULL) {
			$this->totalTime += $time;
		}

		if (($this->longQueryTime !== NULL) && ($time >= $this->longQueryTime)) {
			$this->longQueryCount++;
		}

		if ($this->detectRepeatingQueries && !(bool) \preg_match('#^\s*(BEGIN|COMMIT|ROLLBACK|SET)#i', $query->getSql())) {
			$this->queriesCount[$query->getSql()] = ($this->queriesCount[$query->getSql()] ?? 0) + 1;
		}

		$this->queries[] = [$query, $time, $explain ? self::explain($query) : NULL];
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
					\implode('<br><br>', \array_map(static function ($notice): string {
						return '<em>Notice:</em><br>' . \substr($notice, 9);
					}, \array_map('nl2br', $notices)))
				),
				FALSE,
				NULL,
			];
		}
	}


	/**
	 * @return array<PhPgSql\Db\Row>|NULL
	 */
	private function explain(PhPgSql\Db\Query $query): ?array
	{
		$sql = $query->getSql();

		if (!(bool) \preg_match('#\s*\(?\s*SELECT\s#iA', $sql)) {
			return NULL;
		}

		$explainQuery = new PhPgSql\Db\Sql\Query('EXPLAIN ' . $sql, $query->getParams());

		try {
			$this->disableLogQuery = TRUE;
			$explain = $this->connection->query($explainQuery)->fetchAll();
		} catch (PhPgSql\Db\Exceptions\QueryException $e) {
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
		$totalTime = $this->totalTime;

		$hasWarning = ($this->longQueryCount > 0) || (\count($this->getRepeatingQueries()) > 0) || (\count($this->getNonParsedColumnsQueries()) > 0);

		\ob_start(static function (): void {
		});

		require __DIR__ . '/templates/Panel.tab.phtml';

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
		$totalTime = $this->totalTime;
		$queries = $this->queries;

		$longQueryTime = $this->longQueryTime;

		$longQueryCount = $this->longQueryCount;
		$repeatingQueries = $this->getRepeatingQueries();
		$nonParsedColumnsQueries = $this->getNonParsedColumnsQueries();

		$queryDump = static function (string $sql, array $parameters = []): string {
			return PhPgSql\Db\Helper::dump($sql, $parameters);
		};

		$paramsDump = static function (array $parameters): string {
			return self::paramsDump($parameters);
		};

		\ob_start(static function (): void {
		});

		require __DIR__ . '/templates/Panel.panel.phtml';

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
	 * @return array<array<mixed>>
	 */
	private function getNonParsedColumnsQueries(): array
	{
		if ($this->nonParsedColumnsQueries === NULL) {
			$this->nonParsedColumnsQueries = [];

			/** @var PhPgSql\Db\Result $result */
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


	/**
	 * @param array<mixed> $params
	 */
	private static function paramsDump(array $params): string
	{
		return Tracy\Dumper::toHtml(
			self::printParams($params),
			[
				Tracy\Dumper::DEPTH => Tracy\Debugger::$maxDepth,
				Tracy\Dumper::TRUNCATE => Tracy\Debugger::$maxLength,
			]
		);
	}


	/**
	 * @param array<mixed> $params
	 * @return array<string, mixed>
	 */
	private static function printParams(array $params): array
	{
		$keys = \range(1, \count($params));

		\array_walk($keys, static function (&$value): void {
			$value = '$' . $value;
		});

		return (array) \array_combine($keys, \array_values($params));
	}


	public static function initializePanel(
		PhPgSql\Db\Connection $connection,
		string $name,
		bool $explain,
		bool $notices,
		?float $longQueryTime = NULL,
		bool $detectRepeatingQueries = FALSE,
		bool $detectNonParsedColumns = FALSE
	): self
	{
		$panel = new self($connection, $name, $explain, $notices, $longQueryTime, $detectRepeatingQueries, $detectNonParsedColumns);
		Tracy\Debugger::getBar()->addPanel($panel);
		return $panel;
	}


	/**
	 * @return array{tab: string, panel: string}|NULL
	 */
	public static function renderException(?\Throwable $e): ?array
	{
		if (!$e instanceof PhPgSql\Db\Exceptions\QueryException) {
			return NULL;
		}

		$query = $e->getQuery();
		if ($query === NULL) {
			return NULL;
		}

		$parameters = '';
		$params = $query->getParams();
		if ($params !== []) {
			$parameters = \sprintf('
				<h3>Parameters:</h3>
				<pre>%s</pre>
			', self::paramsDump($params));
		}

		return [
			'tab' => 'SQL',
			'panel' => \sprintf('
				<h3>Query:</h3>
				<pre>%s</pre>

				%s

				<h3>Binded query:</h3>
				<pre>%s</pre>
			', PhPgSql\Db\Helper::dump($query->getSql()), $parameters, PhPgSql\Db\Helper::dump($query->getSql(), $query->getParams())),
		];
	}

}
