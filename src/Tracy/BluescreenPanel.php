<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Tracy;

class BluescreenPanel
{
	private PhPgSql\Tracy\QueryDumper $queryDumper;


	final public function __construct(PhPgSql\Tracy\QueryDumper $queryDumper)
	{
		$this->queryDumper = $queryDumper;
	}


	/**
	 * @return array{tab: string, panel: string}|NULL
	 */
	public function renderException(\Throwable|NULL $e): array|NULL
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
				<pre class="phpgsql-bluescreen-panel">%s</pre>
			', Helper::dumpParameters($params));
		}

		return [
			'tab' => 'SQL',
			'panel' => \sprintf('
				<h3>Query:</h3>
				<pre>%s</pre>

				%s

				<h3>Binded query:</h3>
				<pre>%s</pre>
			', $this->queryDumper->dump($query->getSql()), $parameters, $this->queryDumper->dump($query->getSql(), $query->getParams())),
		];
	}


	public static function initialize(PhPgSql\Tracy\QueryDumper $queryDumper): void
	{
		Tracy\Debugger::getBlueScreen()->addPanel(static function (\Throwable|NULL $e) use ($queryDumper): array|NULL {
			return (new static($queryDumper))->renderException($e);
		});
	}

}
