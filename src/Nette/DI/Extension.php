<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\DI;

use Doctrine;
use Forrest79\PhPgSql;
use Nette;
use Nette\Schema;
use Tracy;

class Extension extends Nette\DI\CompilerExtension
{
	private bool $debugMode;

	/** @var list<string> */
	private array $names = [];


	public function __construct(bool $debugMode)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration(): void
	{
		$autowired = TRUE;
		foreach ((array) $this->getConfig() as $name => $config) {
			\assert($config instanceof \stdClass);
			$config->autowired ??= $autowired;
			$autowired = FALSE;
			$this->setupDatabase((array) $config, $name);
			$this->names[] = $name;
		}
	}


	/**
	 * @param array<string, mixed> $config
	 */
	private function setupDatabase(array $config, string $name): void
	{
		$builder = $this->getContainerBuilder();

		$connectionFactory = $this->prefix(\sprintf('%s.connection.factory', $name));

		$builder->addDefinition($connectionFactory)
			->setFactory(PhPgSql\Nette\Connection\FluentConnectionFactory::class);

		\assert(\is_bool($config['autowired']));
		$connection = $builder->addDefinition($this->prefix(\sprintf('%s.connection', $name)))
			->setFactory('@' . $connectionFactory . '::create', [
				(array) $config['config'],
				$config['forceNew'],
				$config['async'],
			])
			->setAutowired($config['autowired']);

		if ($config['errorVerbosity'] !== NULL) {
			$connection->addSetup('setErrorVerbosity', [$config['errorVerbosity']]);
		}

		if ($config['asyncWaitSeconds'] !== NULL) {
			$connection->addSetup('setConnectAsyncWaitSeconds', [$config['asyncWaitSeconds']]);
		}

		if ($config['rowFactory'] !== NULL) {
			$connection->addSetup('setRowFactory', [$config['rowFactory']]);
		}

		if ($config['dataTypeParser'] !== NULL) {
			$connection->addSetup('setDataTypeParser', [$config['dataTypeParser']]);
		}

		if ($config['dataTypeCache'] !== NULL) {
			$connection->addSetup('setDataTypeCache', [$config['dataTypeCache']]);
		}

		if ($config['debugger'] === TRUE) {
			if (($config['queryDumper'] === NULL) || ($config['queryDumper'] === FALSE)) {
				$queryDumper = $this->prefix(\sprintf('%s.queryDumper', $name));
				if ($config['queryDumper'] === NULL) {
					if (class_exists(Doctrine\SqlFormatter\SqlFormatter::class)) {
						$queryDumperClass = PhPgSql\Tracy\QueryDumpers\SqlFormatter::class;
					} else {
						$queryDumperClass = PhPgSql\Tracy\QueryDumpers\Basic::class;
					}
				} else {
					$queryDumperClass = PhPgSql\Tracy\QueryDumpers\NullDumper::class;
				}

				$builder->addDefinition($queryDumper)
					->setFactory($queryDumperClass);

				$queryDumper = '@' . $queryDumper;
			} else {
				$queryDumper = $config['queryDumper'];
			}

			\assert(\is_string($config['tracyBluescreenPanelClass']));

			$connection->addSetup(\sprintf('%s::initialize(?)', $config['tracyBluescreenPanelClass']), [
				$queryDumper,
			]);

			if ($this->debugMode) {
				\assert(\is_string($config['tracyBarPanelClass']));

				$connection->addSetup(\sprintf('%s::initialize(?, ?, ?, ?, ?, ?, ?, ?)', $config['tracyBarPanelClass']), [
					'@self',
					$queryDumper,
					$name,
					$config['explain'],
					$config['notices'],
					$config['longQueryTimeMs'],
					$config['repeatingQueries'],
					$config['nonParsedColumns'],
				]);
			}
		}

		if ($config['lazy'] === FALSE) {
			$connection->addSetup('connect');
		}
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($this->names as $name) {
			$connectionFactory = $this->prefix(\sprintf('%s.connection.factory', $name));
			$connectionFactoryType = (string) $builder->getDefinition($connectionFactory)->getType();
			if (!\is_subclass_of($connectionFactoryType, PhPgSql\Nette\Connection\ConnectionCreator::class)) {
				throw new \InvalidArgumentException(\sprintf(
					'Connection factory \'%s\' must implement \'%s\' interface',
					$connectionFactory,
					PhPgSql\Nette\Connection\ConnectionCreator::class,
				));
			}

			$connectionFactoryReflection = new \ReflectionClass($connectionFactoryType);
			$connectionReturnType = $connectionFactoryReflection->getMethod('create')->getReturnType();
			$connectionType = $connectionReturnType === NULL ? '' : $connectionReturnType->getName();
			\assert(\is_string($connectionType));
			if (!\is_subclass_of($connectionType, PhPgSql\Db\Connection::class)) {
				throw new \InvalidArgumentException(\sprintf(
					'Connection factory \'%s\' must return connection that extends \'%s\' in create() method, \'%s\' is returning',
					$connectionFactory,
					PhPgSql\Db\Connection::class,
					$connectionType,
				));
			}

			$service = $builder->getDefinition($this->prefix(\sprintf('%s.connection', $name)));
			\assert($service instanceof Nette\DI\Definitions\ServiceDefinition);
			$service->setType($connectionType);
		}
	}


	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::arrayOf(
			Schema\Expect::structure([
				'config' => Schema\Expect::array(),
				'forceNew' => Schema\Expect::bool(FALSE),
				'async' => Schema\Expect::bool(FALSE),
				'errorVerbosity' => Schema\Expect::int(),
				'asyncWaitSeconds' => Schema\Expect::int(),
				'rowFactory' => Schema\Expect::string(),
				'dataTypeParser' => Schema\Expect::string(),
				'dataTypeCache' => Schema\Expect::string(),
				'lazy' => Schema\Expect::bool(TRUE),
				'autowired' => Schema\Expect::bool(),
				'debugger' => Schema\Expect::bool(\class_exists(Tracy\BlueScreen::class)),
				'tracyBluescreenPanelClass' => Schema\Expect::string(PhPgSql\Tracy\BluescreenPanel::class),
				'tracyBarPanelClass' => Schema\Expect::string(PhPgSql\Tracy\BarPanel::class),
				'queryDumper' => Schema\Expect::mixed(), // null|false|string
				'explain' => Schema\Expect::bool(FALSE),
				'notices' => Schema\Expect::bool(FALSE),
				'longQueryTimeMs' => Schema\Expect::anyOf(Schema\Expect::float(), Schema\Expect::int())->castTo('float'),
				'repeatingQueries' => Schema\Expect::bool(FALSE),
				'nonParsedColumns' => Schema\Expect::bool(FALSE),
			]),
		)->before(static function (array $config): array {
			foreach ($config as $name => $values) {
				if (\is_scalar($values) || (\is_array($values) && $name === 'config')) {
					$config = ['default' => $config];
					break;
				}
			}

			return $config;
		});
	}

}
