<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Nette;
use Tracy;

class Panel implements Tracy\IBarPanel
{
	/** @var bool */
	public static $disabled = FALSE;

	/** @var string */
	private $name;

	/** @var float */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = [];


	private function __construct(PhPgSql\Db\Connection $connection, string $name, bool $explain = FALSE, bool $notices = FALSE)
	{
		$connection->addOnQuery(function (PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query, ?float $time = NULL) use ($explain): void {
			$this->logQuery($query, $time, $explain ? self::explain($connection, $query) : []);
		});
		if ($notices) {
			$connection->addOnQuery(function (PhPgSql\Db\Connection $connection): void {
				$this->logNotices($connection);
			});
			$connection->addOnClose(function (PhPgSql\Db\Connection $connection): void {
				$this->logNotices($connection);
			});
		}
		$this->name = $name;
	}


	public function logQuery(PhPgSql\Db\Query $query, ?float $time = NULL, ?array $explain = NULL): void
	{
		if (self::$disabled) {
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
			$explain,
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


	private static function explain(PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query): ?array
	{
		if (self::$disabled) {
			return NULL;
		}

		$explainQuery = new PhPgSql\Db\Query('EXPLAIN ' . $query->getSql(), $query->getParams());

		try {
			self::$disabled = TRUE;
			$explain = $connection->query($explainQuery)->fetchAll();
		} catch (PhPgSql\Db\Exceptions\QueryException $e) {
			$explain = NULL;
		} finally {
			self::$disabled = FALSE;
		}

		return $explain;
	}


	public static function initializePanel(PhPgSql\Db\Connection $connection, string $name, bool $explain, bool $notices): self
	{
		$panel = new self($connection, $name, $explain, $notices);
		Tracy\Debugger::getBar()->addPanel($panel);
		return $panel;
	}


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
		return \ob_get_clean() ?: '';
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
		return \ob_get_clean() ?: '';
	}

}
