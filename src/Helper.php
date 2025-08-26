<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Tracy;

class Helper
{

	/**
	 * @param list<mixed> $parameters
	 */
	public static function dumpParameters(array $parameters): string
	{
		$dumpParameters = [];

		$i = 1;
		foreach ($parameters as $param) {
			$dumpParameters['$' . $i++] = $param;
		}

		return Tracy\Dumper::toHtml(
			$dumpParameters,
			[
				Tracy\Dumper::LAZY => false,
				Tracy\Dumper::DEPTH => Tracy\Debugger::$maxDepth,
				Tracy\Dumper::TRUNCATE => Tracy\Debugger::$maxLength,
			],
		);
	}

}
