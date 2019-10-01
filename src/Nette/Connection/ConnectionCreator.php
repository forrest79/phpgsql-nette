<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

use Forrest79\PhPgSql;

interface ConnectionCreator
{

	/**
	 * @param string $config
	 * @param bool $forceNew
	 * @param bool $async
	 * @return PhPgSql\Fluent\Connection
	 */
	function create(string $config, bool $forceNew, bool $async);

}
