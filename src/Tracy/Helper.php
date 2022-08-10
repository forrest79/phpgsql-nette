<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy;

use Tracy;

class Helper
{

	/**
	 * @param array<mixed> $parameters
	 */
	public static function dumpParameters(array $parameters): string
	{
		return Tracy\Dumper::toHtml(
			self::prepareParameters($parameters),
			[
				Tracy\Dumper::LAZY => FALSE,
				Tracy\Dumper::DEPTH => Tracy\Debugger::$maxDepth,
				Tracy\Dumper::TRUNCATE => Tracy\Debugger::$maxLength,
			]
		);
	}


	/**
	 * @param array<mixed> $params
	 * @return array<string, mixed>
	 */
	private static function prepareParameters(array $params): array
	{
		$printParams = [];

		$i = 1;
		foreach ($params as $param) {
			$printParams['$' . $i++] = $param;
		}

		return $printParams;
	}

}
