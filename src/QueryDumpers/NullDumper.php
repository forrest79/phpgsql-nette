<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy\QueryDumpers;

use Forrest79\PhPgSql\Tracy;

class NullDumper extends Tracy\QueryDumper
{

	public function format(string $sql): string
	{
		return $sql;
	}

}
