<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Unit\Db;

use Forrest79\PhPgSql;
use Nette\DI;
use Tester;

require_once __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
class Basic extends Tester\TestCase
{

	public function testBasic(): void
	{
		$loader = new DI\Config\Loader;
		$config = $loader->load(Tester\FileMock::create('
		database:
			config: "host=localhost port=5432 user=postgres password=postgres dbname=postgres"
			debugger: no
			lazy: yes
		', 'neon'));

		$compiler = new DI\Compiler;
		$compiler->addExtension('database', new PhPgSql\Nette\DI\Extension(FALSE));
		eval($compiler->addConfig($config)->setClassName('Container1')->compile());

		$container = new \Container1;
		$container->initialize();

		$connection = $container->getService('database.default.connection');
		Tester\Assert::type(PhPgSql\Db\Connection::class, $connection);
	}

}

(new Basic)->run();
