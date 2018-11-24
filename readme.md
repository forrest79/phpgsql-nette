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
	config: 'host=localhost port=5432 user=postgres password=postgres dbname=postgres'
	connectionClass: '\Forrest79\PhPgSql\Fluent\Connection', # you can change connection class, ie basic \Forrest79\PhPgSql\DB\Connection or your own, but every connection class must extends \Forrest79\PhPgSql\Fluent\Connection 
	asyncWaitSeconds: 5 # default is NULL and it will use default seconds value
	forceNew: yes # default is no
	async: yes # default is no
	lazy: no # default is yes
	autowired: no # default is yes (for second and next connection is always no)
	debugger: no # default is yes (when yes, exception panel on Bluescreen is added and Tracy bar is shown in debug mode)
```

Or multiple connections:

```yaml
database:
	first:
		config: 'host=localhost port=5432 user=postgres password=postgres dbname=postgres'
	second:
		config: 'host=localhost port=5433 user=postgres password=postgres dbname=postgres'
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
