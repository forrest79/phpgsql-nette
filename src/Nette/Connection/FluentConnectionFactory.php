<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

use Forrest79\PhPgSql;

class FluentConnectionFactory extends ConnectionFactory
{

	public function create(array $config, bool $forceNew, bool $async): PhPgSql\Fluent\Connection
	{
		return new PhPgSql\Fluent\Connection($this->prepareConfig($config), $forceNew, $async);
	}

}
