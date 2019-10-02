<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

abstract class ConnectionFactory implements ConnectionCreator
{

	protected function prepareConfig(array $config): string
	{
		return \implode(' ', \array_map(static function (string $key, $value) {
			return $key . '=' . $value;
		}, \array_keys($config), \array_values($config)));
	}

}
