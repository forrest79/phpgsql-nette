PhPgSql - Nette
================================================================

Introduction
------------

Nette extension to easy use PhPgSql - Db in Nette application


Installation
------------

The recommended way to install PhPgSql - Nette is through Composer:

TODO
```
composer require forrest79/phpgsql-nette --dev
```

PhPgSql requires PHP 7.1.0 and pgsql binary extension.


Using
-----

First, register extension in neon configuration:

```yaml
extensions:
	database: Forrest79\PhPgSql\Nette\DI\Extension(%debugMode%)
```

Than, register connection, one, default:

```yaml
database:
	config: 'host=localhost port=5432 user=postgres password=postgres dbname=postgres'
	asyncWaitSeconds: NULL # use default value
	forceNew: yes # default is no
	async: yes # default is no
	lazy: no # default is yes
	debugger: no # default is yes
```

Or multiple connections:

```yaml
database:
	first:
		config: 'host=localhost port=5432 user=postgres password=postgres dbname=postgres'
	second:
		config: 'host=localhost port=5433 user=postgres password=postgres dbname=postgres'
```

First `connection` is autowired as `Forrest79\PhPgSql\Db\Connection` or `Forrest79\PhPgSql\Fluent\Connection` if is installed. Or can be get by:

```php
$container->getService('database.default.connection'); // for one connection, default

$container->getService('database.first.connection');
```


Second can be get by:

```php
$container->getService('database.second.connection');
```
