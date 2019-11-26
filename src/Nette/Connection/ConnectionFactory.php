<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

abstract class ConnectionFactory implements ConnectionCreator
{

	protected function prepareConfig(array $config): string
	{
		$configItems = [];
		foreach ($config as $key => $value) {
			if ($value !== NULL) {
				$configItems[] = $key . '=\'' . $value . '\'';
			}
		}
		return \implode(' ', $configItems);
	}

}
