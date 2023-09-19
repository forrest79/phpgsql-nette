<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tracy\QueryDumpers;

use Forrest79\PhPgSql\Tracy;

class Basic extends Tracy\QueryDumper
{
	private const IMPORTANT_KEYWORDS = 'SELECT|INSERT(?:\s+INTO)?|DELETE|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|RETURNING|SET|VALUES|LEFT(?:\s+OUTER)?\s+JOIN|RIGHT(?:\s+OUTER)?\s+JOIN|INNER\s+JOIN|FULL(?:\s+OUTER)?\s+JOIN|CROSS\s+JOIN|TRUNCATE|BEGIN(?:\s+TRANSACTION)?|COMMIT|ROLLBACK(?:\s+TO\s+SAVEPOINT)?|(?:RELEASE\s+)?SAVEPOINT';
	private const OTHER_KEYWORDS = 'ALL|DISTINCT|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|ILIKE|TRUE|FALSE';
	private const VARIABLES = '\$([0-9]+)|(\'[^\']*\')';


	/**
	 * @credit https://github.com/dg/dibi/blob/master/src/Dibi/Helpers.php
	 */
	public function format(string $sql): string
	{
		// insert new lines
		$sql = ' ' . $sql . ' ';
		$sql = (string) \preg_replace(\sprintf('#(?<=[\\s,(])(%s)(?=[\\s,)])#i', self::IMPORTANT_KEYWORDS), "\n\$1", $sql); // intentionally (string), other can't be returned

		// reduce spaces
		$sql = (string) \preg_replace('#[ \t]{2,}#', ' ', $sql); // intentionally (string), other can't be returned

		$sql = \wordwrap($sql, 100);
		$sql = (string) \preg_replace("#([ \t]*\r?\n){2,}#", "\n", $sql); // intentionally (string), other can't be returned

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
			if (isset($m[1]) && ($m[1] !== '')) { // comment
				return \sprintf('<em style="color:gray">%s</em>', $m[1]);
			} elseif (isset($m[2]) && ($m[2] !== '')) { // important keywords
				return \sprintf('<strong style="color:blue">%s</strong>', $m[2]);
			} elseif (isset($m[3]) && ($m[3] !== '')) { // other keywords
				return \sprintf('<strong style="color:green">%s</strong>', $m[3]);
			} elseif (isset($m[4]) && ($m[4] !== '')) { // variables
				return \sprintf('<strong style="color:brown">%s</strong>', $m[4]);
			}
			return \sprintf('<strong style="color:red">%s</strong>', $m[0]); // error
		}, $sql);
	}

}
