# PhPgSql - Nette

[![Latest Stable Version](https://poser.pugx.org/forrest79/phpgsql-nette/v)](//packagist.org/packages/forrest79/phpgsql-nette)
[![Monthly Downloads](https://poser.pugx.org/forrest79/phpgsql-nette/d/monthly)](//packagist.org/packages/forrest79/phpgsql-nette)
[![License](https://poser.pugx.org/forrest79/phpgsql-nette/license)](//packagist.org/packages/forrest79/phpgsql-nette)
[![Build](https://github.com/forrest79/phpgsql-nette/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/forrest79/phpgsql-nette/actions/workflows/build.yml)

* [Nette](https://nette.org)
* [PhPgSql](https://github.com/forrest79/phpgsql)

Use PhPgSql with Nette Framework.

## Introduction

Extension to easy use PhPgSql in Nette application.


## Installation

The recommended way to install PhPgSql - Nette is through Composer:

```sh
composer require forrest79/phpgsql-nette
```

PhPgSql requires PHP 8.1.0 and pgsql binary extension.


## Using

First, register extension in neon configuration:

```yaml
extensions:
    database: Forrest79\PhPgSql\Nette\DI\Extension(%debugMode%)
```

Then, register connection (one connection is as default):

```yaml
database:
    config: # default is empty array, keys and values are not checked, just imploded to `pg_connect` `$connection_string` as `"key1=value1 key2=value2 ..."`
        host: localhost
        port: 5432
        user: postgres
        password: postgres
        dbname: postgres
        connect_timeout: 5 # good habit is to use connect_timeout parameter
    errorVerbosity: ::constant(PGSQL_ERRORS_VERBOSE) # default is null and it will use default error verbose PGSQL_ERRORS_DEFAULT, other value can be PGSQL_ERRORS_TERSE
    asyncWaitSeconds: 5 # default is null and it will use default seconds value
    rowFactory: @App\PhPgSql\Db\RowFactories\MyOwnRowFactory # this service is needed to be registered, default is null, and default row factory is used
    dataTypeParser: @App\PhPgSql\Db\DataTypeParsers\MyOwnDataTypeParser # this service is needed to be registered, default is null, and default data type parser is used
    dataTypeCache: @Forrest79\PhPgSql\Db\DataTypeCaches\PhpFile # this service is needed to be registered like this `- Forrest79\PhPgSql\Db\DataTypeCaches\PhpFile('%tempDir%/phpgsql/data-types-cache.php')`, this is recommended settings, default is null and cache is disabled
    forceNew: true # default is false
    async: true # default is false, when true, connection is made in async way, and it's not blocking the next PHP code execution (before the first query is run, a library is waiting for active connection)
    lazy: false # default is true, when false, connection is made right after the Connection object is created, when true, connection is made with the first query
    autowired: false # default is true (for second and next connection is always false)
    debugger: false # default is true (when true, the exception panel on Bluescreen is added, and Tracy bar is shown in debug mode)
    tracyBluescreenPanelClass: App\PhPgSql\MyOwnTracy\BarPanel # default is Forrest79\PhPgSql\Tracy\BluescreenPanel (you can use your own Tracy bluescreen panel class)
    tracyBarPanelClass: App\PhPgSql\MyOwnTracy\BarPanel # default is Forrest79\PhPgSql\Tracy\BarPanel (you can use your own Tracy bar panel class)
    queryDumper: false # default is null (when false, no query dumper is used, and all SQL queries are displayed as it is, when null - auto-detection is used - when Doctrine\Sql-Formatter is installed, it is used, when not, internal basic formatter is used or use own service via @serviceName)
    explain: true # default is false (when true, if the Tracy panel is enabled, explain is shown for every query)
    notices: true # default is false (when true, if the Tracy panel is enabled, after every SQL command and before connection is closed, notices are got and put into the query log)
    longQueryTimeMs: 100 # default is null = disabled, is set (float, time in milliseconds) and Tracy panel is enabled, all queries that takes longer than this value is marked in the panel with bold red time)
    repeatingQueries: true # default is false (when true, if the Tracy panel is enabled, repeating queries are detected and listed - except BEGIN, COMMIT, ROLLBACK and SET statements)
    nonParsedColumns: true # default is false (when true, if the Tracy panel is enabled, queries with some non-parsed (used) columns are detected and listed)
```

Or multiple connections:

```yaml
database:
    first:
        config:
            host: localhost
            port: 5432
            user: postgres
            password: postgres
            dbname: postgres
    second:
        config:
            host: localhost
            port: 5433
            user: postgres
            password: postgres
            dbname: postgres
```

> IMPORTANT! You can't name connection as `config`.

First `connection` is autowired as `Forrest79\PhPgSql\Fluent\Connection`. If you want to autowired other connection or none connection, you must explicitly set `autowired: false`.

You can also get a connection by:

```php
$container->getService('database.default.connection'); // for one connection, default

$container->getService('database.first.connection');
```

Second can be got by:

```php
$container->getService('database.second.connection');
```

## Use your own connection class

By default `Forrest79\PhPgSql\Fluent\Connection` is registered to DI as connection class. If you want to use other (your own) connection class, you need to use your own connection factory. This is class that implements `Forrest79\PhPgSql\Nette\Connection\ConnectionCreator` interface, and you must specify the concrete return type with your connection class.

Example:

```php
class ConnectionFactory implements Forrest79\PhPgSql\Nette\Connection\ConnectionCreator
{
    /** @var int */
    private $statementTimeout = null;

    public function __construct(int $sessionTimeout)
    {
        $this->statementTimeout = $sessionTimeout;
    }

    /**
     * In `$config` array are all values from connection config definition, you can use some special/meta values for your own logic and unset it from `$config` before sending it to `prepareConfig()` function.
     */
    public function create(array $config, bool $forceNew, bool $async): MyOwnConnection
    {
        return (new Connection(
            $this->prepareConfig($config), // this will implode array config to string, you can extend this method and add some default settings or your own logic
            $forceNew,
            $async,
        ))->addOnConnect(function(Forrest79\PhPgSql\Db\Connection $connection) {
            $connection->query(sprintf('SET statement_timeout = %d', $this->statementTimeout));
        });
    }

    protected function prepareConfig(array $config): string
    {
        return parent::prepareConfig($config + ['connect_timeout' => 5]);
    }
}
```

Function `prepareConfig(array $config)` create connection string (`key='value' key='value' ...`) from the array with configuration. Items with `null` values are skipped.

And now, you need to override old connection factory with this one in DI configuration, `services` section, like this:

```yaml
services:
    database.default.connection.factory: ConnectionFactory(15)
```

Where `default` is connection name.

## Query dumper

SQL queries are dumped in `Tracy\Bar` and in `Tracy\Bluescreen` and you can use different dumpers/formatters. Three are included:
- `PhPgSql\Tracy\QueryDumpers\NullDumper` - show SQL query as it is, it is used when `queryDumper: false` is set
- `PhPgSql\Tracy\QueryDumpers\Basic` - highlight and format SQL query with basic internal formatter, it is used when `queryDumper: null` and `Doctrine\Sql-Formatter` is not installed
- `PhPgSql\Tracy\QueryDumpers\SqlFormatter` - highlight and format SQL query with [Doctrine\Sql-Formatter](https://github.com/doctrine/sql-formatter), it is used when `queryDumper: null` and `Doctrine\Sql-Formatter` is installed

> You can install better formatting via `Doctrine\Sql-Formatter` just for your dev environment with `composer require doctrine/sql-formatter --dev`.

Or you can use your own query dumper, just create a class-extending abstract class `PhPgSql\Tracy\QueryDumper`, register it to the DI container and set service to `queryDumper: @class/serviceName`

## Tracy BarPanel

`BarPanel` for `Tracy` with DB queries is showing links to PHP source files, where the query was sent to the database. This is made using PHP function `debug_backtrace()`.
This function returns the whole call stack. To get the correct place in the PHP source file, we must ignore some internal library classes where the query is sent to the database in real.
Basically, `BarPanel` ignores all classes/function from PhPgSql library to show the most accurate place in the PHP source.

When you use some own custom wrapping objects, you want to ignore in the call stack, you can extend `backtraceContinueIterate()` method and add your own logic here. For example:

```php
protected static function backtraceContinueIterate(string $class, string $function): bool
{
    return parent::backtraceContinueIterate() // just for sure, you can use multiple extends...
        || (is_a($class, MyOwnFluentQuery::class, true) && ($function === 'count'))
        || (is_a($class, Mapper\Record::class, true) && ($function === 'fetch'));
}
```

- by default, last 1 000 SQL queries are displayed. You can set your own limit with static property `Forrest79\PhPgSql\Tracy\BarPanel::$showMaxLastQueries = 10000` 
- bar can be disabled with static property `Forrest79\PhPgSql\Tracy\BarPanel::$disabled = true`
