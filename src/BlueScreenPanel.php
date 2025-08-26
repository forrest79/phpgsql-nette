<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Forrest79\PhPgSql;
use Tracy;

class BlueScreenPanel
{
	private PhPgSql\Tracy\QueryDumper $queryDumper;


	final public function __construct(PhPgSql\Tracy\QueryDumper $queryDumper)
	{
		$this->queryDumper = $queryDumper;
	}


	/**
	 * @return array{tab: string, panel: string}|null
	 */
	public function renderException(\Throwable|null $e): array|null
	{
		if (!$e instanceof PhPgSql\Db\Exceptions\QueryException) {
			return null;
		}

		$query = $e->getQuery();
		if ($query === null) {
			return null;
		}

		$parameters = '';
		$queryParams = $query->params;
		if ($queryParams !== []) {
			$parameters = \sprintf('
				<h3>Parameters:</h3>
				<pre class="phpgsql-bluescreen-panel">%s</pre>
			', Helper::dumpParameters($queryParams));
		}

		return [
			'tab' => 'SQL',
			'panel' => \sprintf('
				<h3>Query:</h3>
				<pre>%s</pre>

				%s

				<h3>Binded query:</h3>
				<pre>%s</pre>
			', $this->queryDumper->dump($query->sql), $parameters, $this->queryDumper->dump($query->sql, $query->params)),
		];
	}


	public static function initialize(Tracy\BlueScreen $tracyBlueScreen, PhPgSql\Tracy\QueryDumper $queryDumper): void
	{
		$panel = new static($queryDumper);
		$tracyBlueScreen->addPanel(static function (\Throwable|null $e) use ($panel): array|null {
			return $panel->renderException($e);
		});
	}

}
