<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\Connection;

abstract class ConnectionFactory implements ConnectionCreator
{

	protected function prepareConfig(array $config): string
	{
		$dbConfig = [];
		foreach ($config as $key => $value) {
			if (($value !== '') && ($value !== NULL)) {
				$dbConfig[] = $key . '=' . $value;
			}
		}
		return \implode(' ', $dbConfig);
	}

}
