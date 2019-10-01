<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

use Forrest79\PhPgSql;

class FluentConnectionFactory implements ConnectionCreator
{

	public function create(string $config, bool $forceNew, bool $async): PhPgSql\Fluent\Connection
	{
		return new PhPgSql\Fluent\Connection($config, $forceNew, $async);
	}

}
