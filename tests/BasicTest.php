<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests;

use Forrest79\PhPgSql;
use Nette\DI;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
class BasicTest extends TestCase
{

	public function testBasic(): void
	{
		$loader = new DI\Config\Loader();
		$config = $loader->load(Tester\FileMock::create('
		database:
			debugger: false
			config:
				host: localhost
				port: 5432
				user: postgres
				password: postgres
				dbname: postgres
				connect_timeout: 5
			lazy: true
		', 'neon'));

		$containerName = 'Container' . \uniqid();

		$compiler = new DI\Compiler();
		$compiler->addExtension('database', new PhPgSql\Nette\DI\Extension(FALSE));
		eval($compiler->addConfig($config)->setClassName($containerName)->compile());

		$container = new $containerName();
		$container->initialize();

		$connection = $container->getService('database.default.connection');
		Tester\Assert::type(PhPgSql\Db\Connection::class, $connection);
	}

}

(new BasicTest())->run();
