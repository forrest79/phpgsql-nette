<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests;

use Forrest79\PhPgSql;
use Nette\DI;
use Tester;
use Tracy;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class BasicTest extends TestCase
{

	public function testBasic(): void
	{
		$this->checkConnection('
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
		');
	}


	public function testOnlyConfig(): void
	{
		$this->checkConnection('
			database:
				config:
					host: localhost
					port: 5432
					user: postgres
					password: postgres
					dbname: postgres
					connect_timeout: 5
		');
	}


	private function checkConnection(string $configNeon): void
	{
		$loader = new DI\Config\Loader();
		$config = $loader->load(Tester\FileMock::create($configNeon, 'neon'));

		$containerName = 'Container' . \uniqid();

		$compiler = new DI\Compiler();
		$compiler->addExtension('tracy', new Tracy\Bridges\Nette\TracyExtension());
		$compiler->addExtension('database', new PhPgSql\Nette\DI\Extension(FALSE));
		eval($compiler->addConfig($config)->setClassName($containerName)->compile());

		$container = new $containerName();
		$container->initialize();

		$connection = $container->getService('database.default.connection');
		Tester\Assert::type(PhPgSql\Db\Connection::class, $connection);
	}

}

(new BasicTest())->run();
