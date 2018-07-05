<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Tracy;

class Panel implements Tracy\IBarPanel
{
	/** @var bool */
	public static $disabled = FALSE;

	/** @var string */
	private $name;

	/** @var int */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = [];


	private function __construct(PhPgSql\Db\Connection $connection, string $name)
	{
		$connection->addOnConnect(function() {
			$this->logConnect();
		});
		$connection->addOnClose(function() {
			$this->logClose();
		});
		$connection->addOnQuery(function(PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query, ?float $time = NULL) {
			$this->logQuery($query, $time);
		});
		$this->name = $name;
	}


	private function logConnect(): void
	{
		if (self::$disabled) {
			return;
		}

		$this->queries[] = ['<pre class="dump"><strong style="color:blue">[CONNECT]</strong></pre>', NULL, NULL, FALSE];
	}


	private function logClose(): void
	{
		if (self::$disabled) {
			return;
		}

		$this->queries[] = ['<pre class="dump"><strong style="color:blue">[CLOSE]</strong></pre>', NULL, NULL, FALSE];
	}


	public function logQuery(PhPgSql\Db\Query $query, ?float $time = NULL): void
	{
		if (self::$disabled) {
			return;
		}

		$this->count++;

		if ($time) {
			$this->totalTime += $time;
		}

		$params = $query->getParams();
		$this->queries[] = [
			PhPgSql\Db\Helper::dump($query->getSql()),
			$params ? Tracy\Debugger::dump(self::printParams($params), TRUE) : NULL,
			PhPgSql\Db\Helper::dump($query->getSql(), $query->getParams()),
			$time,
		];
	}


	public static function initializePanel(PhPgSql\Db\Connection $connection, string $name): self
	{
		$panel = new self($connection, $name);
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
		if ($params) {
			$parameters = sprintf('
				<h3>Parameters:</h3>
				<pre>%s</pre>
			', Tracy\Debugger::dump(self::printParams($params), TRUE));
		}

		return [
			'tab' => 'SQL',
			'panel' => sprintf('
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
		$keys = range(1, count($params));
		array_walk($keys, function(&$value) {
			$value = '$' . $value;
		});
		return array_combine($keys, array_values($params));
	}


	public function getTab(): string
	{
		$name = $this->name;
		$count = $this->count;
		$totalTime = $this->totalTime;
		ob_start(function () {});
		require __DIR__ . '/templates/Panel.tab.phtml';
		return ob_get_clean();
	}


	public function getPanel(): ?string
	{
		if (!$this->count) {
			return NULL;
		}

		$name = $this->name;
		$count = $this->count;
		$totalTime = $this->totalTime;
		$queries = $this->queries;

		ob_start(function () {});
		require __DIR__ . '/templates/Panel.panel.phtml';
		return ob_get_clean();
	}

}
