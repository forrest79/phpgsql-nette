<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy\QueryDumpers;

use Forrest79\PhPgSql\Tracy;

class Basic extends Tracy\QueryDumper
{
	private const string IMPORTANT_KEYWORDS = 'SELECT|INSERT(?:\s+INTO)?|DELETE|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|RETURNING|SET|VALUES|LEFT(?:\s+OUTER)?\s+JOIN|RIGHT(?:\s+OUTER)?\s+JOIN|INNER\s+JOIN|FULL(?:\s+OUTER)?\s+JOIN|CROSS\s+JOIN|TRUNCATE|BEGIN(?:\s+TRANSACTION)?|COMMIT|ROLLBACK(?:\s+TO\s+SAVEPOINT)?|(?:RELEASE\s+)?SAVEPOINT';
	private const string OTHER_KEYWORDS = 'ALL|DISTINCT|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|ILIKE|TRUE|FALSE';
	private const string VARIABLES = '\$([0-9]+)|(\'[^\']*\')';


	/**
	 * @credit https://github.com/dg/dibi/blob/master/src/Dibi/Helpers.php
	 */
	public function format(string $sql): string
	{
		// insert new lines
		$sql = ' ' . $sql . ' ';
		$sql = \preg_replace(\sprintf('#(?<=[\\s,(])(%s)(?=[\\s,)])#i', self::IMPORTANT_KEYWORDS), "\n\$1", $sql);
		\assert(\is_string($sql));

		// reduce spaces
		$sql = \preg_replace('#[ \t]{2,}#', ' ', $sql);
		\assert(\is_string($sql));

		$sql = \wordwrap($sql, 100);
		$sql = \preg_replace("#([ \t]*\r?\n){2,}#", "\n", $sql);
		\assert(\is_string($sql));

		// syntax highlight
		$highlighter = \sprintf(
			'#(/\\*.+?\\*/)|(?<=[\\s,(])(%s)(?=[\\s,)])|(?<=[\\s,(=])(%s)(?=[\\s,)=])|(%s)#is',
			self::IMPORTANT_KEYWORDS,
			self::OTHER_KEYWORDS,
			self::VARIABLES,
		);

		$sql = \htmlspecialchars($sql, \ENT_COMPAT);

		/** @phpstan-var string */
		return \preg_replace_callback($highlighter, static function (array $m): string {
			if ($m[1] !== '') { // comment
				return \sprintf('<em style="color:gray">%s</em>', $m[1]);
			} elseif ($m[2] !== '') { // important keywords
				return \sprintf('<strong style="color:blue">%s</strong>', $m[2]);
			} elseif ($m[3] !== '') { // other keywords
				return \sprintf('<strong style="color:green">%s</strong>', $m[3]);
			} elseif ($m[4] !== '') { // variables
				return \sprintf('<strong style="color:brown">%s</strong>', $m[4]);
			}
		}, $sql);
	}

}
