# PhPgSql - Nette

[![Latest Stable Version](https://poser.pugx.org/forrest79/phpgsql-nette/v)](//packagist.org/packages/forrest79/phpgsql-nette)
[![Monthly Downloads](https://poser.pugx.org/forrest79/phpgsql-nette/d/monthly)](//packagist.org/packages/forrest79/phpgsql-nette)
[![License](https://poser.pugx.org/forrest79/phpgsql-nette/license)](//packagist.org/packages/forrest79/phpgsql-nette)
[![Build](https://github.com/forrest79/PhPgSql-Nette/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/forrest79/PhPgSql-Nette/actions/workflows/build.yml)

* [Nette](https://nette.org)
* [PhPgSql](https://github.com/forrest79/PhPgSql)

Use PhPgSql with Nette Framework.

## Introduction

Nette extension to easy use PhPgSql in Nette application.


## Installation

The recommended way to install PhPgSql - Nette is through Composer:

```sh
composer require forrest79/phpgsql-nette
```

PhPgSql requires PHP 7.1.0 and pgsql binary extension.


## Using

First, register extension in neon configuration:

```yaml
extensions:
    database: Forrest79\PhPgSql\Nette\DI\Extension(%debugMode%)
```

Than, register connection (one connection is as default):

```yaml
database:
    config: # default is empty array, keys and values are not checked, just imploded to `pg_connect` `$connection_string` as `"key1=value1 key2=value2 ..."`
        host: localhost
        port: 5432
        user: postgres
        password: postgres
        dbname: postgres
        connect_timeout: 5 # good habit is to use connect_timeout parameter
    errorVerbosity: ::constant(PGSQL_ERRORS_VERBOSE) # default is NULL and it will use default error verbose PGSQL_ERRORS_DEFAULT, other value can be PGSQL_ERRORS_TERSE
    asyncWaitSeconds: 5 # default is NULL and it will use default seconds value
    defaultRowFactory: @App\PhPgSql\Db\RowFactories\MyOwnRowFactory # this service is needed to be registered, default is NULL and default row factory is used
    dataTypeParser: @App\PhPgSql\Db\DataTypeParsers\MyOwnDataTypeParser # this service is needed to be registered, default is NULL and default data type parser is used
    dataTypeCache: @Forrest79\PhPgSql\Db\DataTypeCaches\PhpFile # this service is needed to be registered like this `- Forrest79\PhPgSql\Db\DataTypeCaches\PhpFile('%tempDir%/phpgsql/data-types-cache.php')`, this is recommended settings, default is NULL and cache is disabled
    forceNew: true # default is false
    async: true # default is false, when true, connection is made in async way and it's not blocking next PHP code execution (before first query is run, library is waiting for active connection)
    lazy: false # default is true, when false, connection is made right after Connection object is created, when true, connection is made with the first query
    autowired: false # default is true (for second and next connection is always false)
    debugger: false # default is true (when true, exception panel on Bluescreen is added and Tracy bar is shown in debug mode)
    explain: true # default is false (when true, if Tracy panel is enabled, explain is shown for every query)
    notices: true # default is false (when true, if Tracy panel is enabled, after every SQL command and before connection is closed notices are got and put into queries log)
    longQueryTime: 0.1 # default is NULL = disabled, is set (float, time in second) and Tracy panel is enabled, all queries that takes longer than this value are marked in panel with bold red time)
    repeatingQueries: true # default is FALSE (when true, if Tracy panel is enabled, repeating queries are detected and listed - except BEGIN, COMMIT, ROLLBACK and SET statements)
    nonParsedColumns: true # default is FALSE (when true, if Tracy panel is enabled, queries with some non parsed (used) columns are detected and listed)
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

You can also get connection by:

```php
$container->getService('database.default.connection'); // for one connection, default

$container->getService('database.first.connection');
```

Second can be get by:

```php
$container->getService('database.second.connection');
```

## Use your own connection class

By default `Forrest79\PhPgSql\Fluent\Connection` is registered to DI as connection class. If you want to use other (your own) connection class, you need to use own connection factory. This is class that implements `Forrest79\PhPgSql\Nette\Connection\ConnectionCreator` interface and you must specify concrete return type with your connection class.

Example:

```php
class ConnectionFactory implements Forrest79\PhPgSql\Nette\Connection\ConnectionCreator
{
    /** @var int */
    private $statementTimeout = NULL;

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

Function `prepareConfig(array $config)` create connection string (`key='value' key='value' ...`) from array with configuration. Items with `NULL` values are skipped.

And now, you just need to override old connection factory with this one in DI configuration, `services` section, like this:

```yaml
services:
    database.default.connection.factory: ConnectionFactory(15)
```

Where `default` is connection name.
