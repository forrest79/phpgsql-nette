<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

use Forrest79\PhPgSql;

interface ConnectionCreator
{

	/**
	 * @param array<string, string|int|float|null> $config
	 */
	function create(array $config, bool $forceNew, bool $async): PhPgSql\Fluent\Connection;

}
