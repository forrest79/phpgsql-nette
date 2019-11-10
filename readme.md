# PhPgSql - Nette

[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](https://github.com/forrest79/PhPgSql-Nette/blob/master/license.md)
[![Build Status](https://travis-ci.org/forrest79/PhPgSql-Nette.svg?branch=master)](https://travis-ci.org/forrest79/PhPgSql-Nette)

Use PhPgSql with Nette Framework.

## Introduction

Nette extension to easy use PhPgSql in Nette application.


## Installation

The recommended way to install PhPgSql - Nette is through Composer:

```sh
composer require forrest79/phpgsql-nette --dev
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
    forceNew: yes # default is no
    async: yes # default is no
    lazy: no # default is yes
    autowired: no # default is yes (for second and next connection is always no)
    debugger: no # default is yes (when yes, exception panel on Bluescreen is added and Tracy bar is shown in debug mode)
    explain: yes # default is no (when yes, if Tracy panel is enabled, explain is shown for every query) 
    notices: yes # default is no (when yes, if Tracy panel is enabled, after every SQL command and before connection is closed notices are got and put into queries log) 
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

First `connection` is autowired as `Forrest79\PhPgSql\Fluent\Connection`. Or can be get by:

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

And now, you just need to override old connection factory with this one in DI configuration, `services` section, like this:

```yaml
services:
    database.default.connection.factory: ConnectionFactory(15)
```

Where `default` is connection name.
