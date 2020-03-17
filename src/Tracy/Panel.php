<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Nette;
use Tracy;

class Panel implements Tracy\IBarPanel
{
	/** @var bool */
	public static $disabled = FALSE;

	/** @var PhPgSql\Db\Connection */
	private $connection;

	/** @var string */
	private $name;

	/** @var float */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array<int, mixed> */
	private $queries = [];

	/** @var bool */
	private $disableLogQuery = FALSE;


	private function __construct(PhPgSql\Db\Connection $connection, string $name, bool $explain = FALSE, bool $notices = FALSE)
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

		$this->connection = $connection;
		$this->name = $name;
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

		$params = $query->getParams();
		$this->queries[] = [
			PhPgSql\Db\Helper::dump($query->getSql()),
			($params !== [] ? Tracy\Debugger::dump(self::printParams($params), TRUE) : NULL), // @hack surrounding parentheses are because of phpcs
			($params !== [] ? PhPgSql\Db\Helper::dump($query->getSql(), $query->getParams()) : NULL), // @hack surrounding parentheses are because of phpcs
			$time,
			$explain ? self::explain($query) : [],
		];
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
				NULL,
				NULL,
				FALSE,
				NULL,
			];
		}
	}


	/**
	 * @return array<PhPgSql\Db\Row>|null
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


	public static function initializePanel(PhPgSql\Db\Connection $connection, string $name, bool $explain, bool $notices): self
	{
		$panel = new self($connection, $name, $explain, $notices);
		Tracy\Debugger::getBar()->addPanel($panel);
		return $panel;
	}


	/**
	 * @return array<string, string>|NULL
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
			', Tracy\Debugger::dump(self::printParams($params), TRUE));
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
		$paramsToPrint = \array_combine($keys, \array_values($params));
		if ($paramsToPrint === FALSE) {
			throw new Nette\InvalidArgumentException();
		}
		return $paramsToPrint;
	}


	public function getTab(): string
	{
		$name = $this->name;
		$count = $this->count;
		$totalTime = $this->totalTime;
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

		\ob_start(static function (): void {
		});
		require __DIR__ . '/templates/Panel.panel.phtml';

		$data = \ob_get_clean();

		return $data === FALSE ? '' : $data;
	}

}
