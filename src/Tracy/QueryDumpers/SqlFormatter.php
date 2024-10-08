<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy\QueryDumpers;

use Doctrine;
use Forrest79\PhPgSql\Tracy;

class SqlFormatter extends Tracy\QueryDumper
{

	public function format(string $sql): string
	{
		$formatted = (new Doctrine\SqlFormatter\SqlFormatter(new Doctrine\SqlFormatter\HtmlHighlighter()))->format($sql);
		if (\preg_match('#<pre style=".*?">(.*)<\/pre>$#s', $formatted, $matched) === 1) {
			return $matched[1];
		} else {
			throw new \RuntimeException('Formatted HTML is in bad format.');
		}
	}

}
