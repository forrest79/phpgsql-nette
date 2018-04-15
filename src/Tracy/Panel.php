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

	/** @var int */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = [];


	private function __construct(PhPgSql\Db\Connection $connection, string $name)
	{
		$connection->addOnConnectListener(function() {
			$this->logConnect();
		});
		$connection->addOnCloseListener(function() {
			$this->logClose();
		});
		$connection->addOnQueryListener(function(PhPgSql\Db\Connection $connection, PhPgSql\Db\Query $query, ?float $time = NULL) {
			$this->logQuery($query, $time);
		});
		$this->name = $name;
	}


	private function logConnect(): void
	{
		if (self::$disabled) {
			return;
		}

		$this->queries[] = ['<em>[CONNECT]</em>', NULL, NULL, FALSE];
	}


	private function logClose(): void
	{
		if (self::$disabled) {
			return;
		}

		$this->queries[] = ['<em>[CLOSE]</em>', NULL, NULL, FALSE];
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

		$this->queries[] = [
			self::dumpQuery($query->getSql(), 100),
			Tracy\Debugger::dump($query->getParams(), TRUE),
			self::dumpQuery($query->getSql(), 100, $query->getParams()),
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
		if ($query->getParams()) {
			$parameters = sprintf('
				<h3>Parameters:</h3>
				<pre>%s</pre>
			', Tracy\Debugger::dump($query->getParams(), TRUE));
		}

		return [
			'tab' => 'SQL',
			'panel' => sprintf('
				<h3>Query:</h3>
				<pre>%s</pre>

				%s

				<h3>Binded query:</h3>
				<pre>%s</pre>
			', self::dumpQuery($query->getSql(), 150), $parameters, self::dumpQuery($query->getSql(), 150, $query->getParams())),
		];
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


	/**
	 * @author David Grudl
	 */
	public static function dumpQuery(string $sql, $wordwrap = NULL, array $parameters = []): string
	{
		static $keywords1 = 'CONNECT|BEGIN\s+TRANSACTION|COMMIT|ROLLBACK|SELECT|UPDATE|INSERT(?:\s+INTO)?|DELETE|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|UNION\s+ALL|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE|RETURNING';
		static $keywords2 = 'DISTINCT|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|TRUE|FALSE';
		static $keywords3 = '\$([0-9]+)|(\'[^\']*\')';
		static $callback;

		if ($callback === NULL) {
			$callback = function ($matches) {
				if (!empty($matches[1])) { // comment
					return '<em style="color:gray">' . $matches[1] . '</em>';
				}

				if (!empty($matches[2])) { // error
					return '<strong style="color:red">' . $matches[2] . '</strong>';
				}

				if (!empty($matches[3])) { // most important keywords
					return '<strong style="color:blue">' . $matches[3] . '</strong>';
				}

				if (!empty($matches[4])) { // other keywords
					return '<strong style="color:green">' . $matches[4] . '</strong>';
				}

				if (!empty($matches[5])) { // variables
					return '<strong style="color:brown">' . $matches[5] . '</strong>';
				}
			};
		}

		// insert new lines
		$sql = " $sql ";
		$sql = \preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

		// reduce spaces
		$sql = \preg_replace('#[ \t]{2,}#', " ", $sql);

		if ($wordwrap !== FALSE) {
			$sql = \wordwrap($sql, $wordwrap === NULL ? 100 : $wordwrap);
		}
		$sql = \htmlSpecialChars($sql);
		$sql = \preg_replace("#([ \t]*\r?\n){2,}#", "\n", $sql);

		// syntax highlight
		$sql = \preg_replace_callback("#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])|($keywords3)#is", $callback, $sql);
		$sql = \trim($sql);

		if ($parameters) {
			$sql = preg_replace_callback(
				'/\$(\d+)/',
				function ($matches) use (& $parameters) {
					$i = $matches[1] - 1;

					if (isset($parameters[$i])) {
						$value = str_replace('\'', '\'\'', $parameters[$i]);
						unset($parameters[$i]);
						return '\'' . $value . '\'';
					}

					return $matches[0];
				},
				$sql
			);
		}

		return $sql;
	}

}
