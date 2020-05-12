<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests;

use Forrest79\PhPgSql;
use Nette\DI;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
class MultipleTest extends TestCase
{

	public function testBasic(): void
	{
		$loader = new DI\Config\Loader();
		$config = $loader->load(Tester\FileMock::create('
		database:
			first:
				config:
					host: localhost
					port: 5432
					user: postgres
					password: postgres
					dbname: postgres
				debugger: false
				lazy: true

			second:
				config:
					host: localhost
					port: 5433
					user: postgres
					password: postgres
					dbname: postgres
				debugger: false
				lazy: true
		', 'neon'));

		$compiler = new DI\Compiler();
		$compiler->addExtension('database', new PhPgSql\Nette\DI\Extension(FALSE));
		$containerName = 'Container' . \uniqid();
		eval($compiler->addConfig($config)->setClassName($containerName)->compile());

		$container = new $containerName();
		$container->initialize();

		$connection = $container->getService('database.first.connection');
		Tester\Assert::type(PhPgSql\Db\Connection::class, $connection);

		$connection = $container->getService('database.second.connection');
		Tester\Assert::type(PhPgSql\Db\Connection::class, $connection);
	}

}

(new MultipleTest())->run();
