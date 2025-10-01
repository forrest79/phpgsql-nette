# PhPgSql - Tracy

[![Latest Stable Version](https://poser.pugx.org/forrest79/phpgsql-tracy/v)](//packagist.org/packages/forrest79/phpgsql-tracy)
[![Monthly Downloads](https://poser.pugx.org/forrest79/phpgsql-tracy/d/monthly)](//packagist.org/packages/forrest79/phpgsql-tracy)
[![License](https://poser.pugx.org/forrest79/phpgsql-tracy/license)](//packagist.org/packages/forrest79/phpgsql-tracy)
[![Build](https://github.com/forrest79/phpgsql-tracy/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/forrest79/phpgsql-tracy/actions/workflows/build.yml)

* [PhPgSql](https://github.com/forrest79/phpgsql)
* [Tracy](https://tracy.nette.org/cs/)

Use PhPgSql with Tracy from Nette Framework.

## Introduction

Tracy bar panel to show queries executed in your database and bluescreen extension to show failed database query. 


## Installation

The recommended way to install PhPgSql - Tracy is through Composer:

```sh
composer require forrest79/phpgsql-nette
```


## Using

### Bar panel

Bar panel will show all queries executed in your database. You copy query to the clipboard, mark long run queries, mark repeated queries, show notices and non parsed columns.

You can add the panel for each database connection:

```php
$tracyBar = Tracy\Debugger::getBar(); // or instance get from DI container or somewhere else
$connection = new Forrest79\PhPgSql\Db\Connection(); // or instance get from DI container or somewhere else 
$queryDumper = new PhPgSql\Tracy\QueryDumpers\SqlFormatter(); // or some other query dumper
$name = 'Main connection'; // just to recognize two different bars

$barPanel = PhPgSql\Tracy\BarPanel::initialize(
    $tracyBar,
    $connection,
    $queryDumper,
    $name,
    explain: false, // when true, explain is execute and show for all queries
    notices: false, // when true, notices are get from the database and show with the queries
    longQueryTimeMs: null, // if you set some value in miliseconds, long running queries will be marked
    detectRepeatingQueries: false, // when true, repeating queries will be detected and marked
    detectNonParsedColumns: false, // when true, non-used columns wil be detected and show for each query
    backtraceContinueIterate: null, // used for detection where the query was executed in the PHP code - callable, more about this later
    showMaxLastQueries: null, // show only last queries, when null - default value of 1000 queries is used, selected value otherwise 
);

// To disable bar panel

$barPanel->disable();
```

- bar can be disabled with `disable()` method


### `$backtraceContinueIterate` parameter

Panel is showing links to the PHP source files, where the query was executed. This is made using PHP function `debug_backtrace()`.
This function returns the whole call stack. To get the correct place in the PHP source file, we must ignore some internal library classes where the query
is really executed and iterated up to the user code, that run this execution.

Basically, `BarPanel` ignores all classes/function from PhPgSql library to show the most accurate place in the PHP source.

When you use some own custom wrapping objects, you want to ignore it in the call stack as well. You can some callable and pass it to the  `$backtraceContinueIterate` Parameter. For example:

```php
$backtraceContinueIterate = function (string $class, string $function): bool
{
    return (is_a($class, MyOwn\Database\Repository::class, true) && in_array($function, ['get', 'insert', 'insertReturning', 'multiInsert', 'update', 'updateReturning', 'saveBy', 'delete', 'deleteReturning', 'getNextId'], true))
        || (is_a($class, MyOwn\Database\Transaction::class, true) && in_array($function, ['execute', 'beginSmart', 'commitSmart', 'rollbackSmart'], true))
        || (is_a($class, MyOwn\Database\DbFunction::class, true) && in_array($function, ['fetch', 'run', 'from'], true))
        || (is_a($class, MyOwn\Database\Fluent\Query::class, true) && in_array($function, ['count', 'exists', 'fetchSingleValue'], true));
}
```

### Bluescreen

Bluescreen should be registered just once (it doesn't matter how many DB connections you have):

```php
$tracyBlueScreen = Tracy\Debugger::getBlueScreen(); // or instance get from DI container or somewhere else
$queryDumper = new PhPgSql\Tracy\QueryDumpers\SqlFormatter(); // or some other query dumper
PhPgSql\Tracy\BlueScreenPanel::initialize($tracyBlueScreen, $queryDumper); 
```

With this, when some `Forrest79\PhPgSql\Db\Exceptions\QueryException` is thrown, you will see the SQL query and the parameters (and the SQL query with the parameters) on your BlueScreen.

## Query dumper

SQL queries are dumped in `Tracy\Bar` and in `Tracy\BlueScreen` and you can use different dumpers/formatters. Three are included:

- `PhPgSql\Tracy\QueryDumpers\NullDumper` - show SQL query as it is
- `PhPgSql\Tracy\QueryDumpers\Basic` - highlight and format SQL query with basic internal formatter
- `PhPgSql\Tracy\QueryDumpers\SqlFormatter` - highlight and format SQL query with [Doctrine\Sql-Formatter](https://github.com/doctrine/sql-formatter), `Doctrine\Sql-Formatter` must be is installed (`composer require doctrine/sql-formatter --dev`)

Or you can use your own query dumper, just create a class-extending abstract class `PhPgSql\Tracy\QueryDumper`.
