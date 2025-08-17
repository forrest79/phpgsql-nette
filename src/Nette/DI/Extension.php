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
		$autowired = true;
		foreach ((array) $this->getConfig() as $name => $config) {
			\assert($config instanceof \stdClass);
			$config->autowired ??= $autowired;
			$autowired = false;
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

		if ($config['errorVerbosity'] !== null) {
			$connection->addSetup('setErrorVerbosity', [$config['errorVerbosity']]);
		}

		if ($config['asyncWaitSeconds'] !== null) {
			$connection->addSetup('setConnectAsyncWaitSeconds', [$config['asyncWaitSeconds']]);
		}

		if ($config['rowFactory'] !== null) {
			$connection->addSetup('setRowFactory', [$config['rowFactory']]);
		}

		if ($config['dataTypeParser'] !== null) {
			$connection->addSetup('setDataTypeParser', [$config['dataTypeParser']]);
		}

		if ($config['dataTypeCache'] !== null) {
			$connection->addSetup('setDataTypeCache', [$config['dataTypeCache']]);
		}

		if ($config['debugger'] === true) {
			if (($config['queryDumper'] === null) || ($config['queryDumper'] === false)) {
				$queryDumper = $this->prefix(\sprintf('%s.queryDumper', $name));
				if ($config['queryDumper'] === null) {
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

		if ($config['lazy'] === false) {
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
			$connectionType = $connectionReturnType === null ? '' : $connectionReturnType->getName();
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
				'forceNew' => Schema\Expect::bool(false),
				'async' => Schema\Expect::bool(false),
				'errorVerbosity' => Schema\Expect::int(),
				'asyncWaitSeconds' => Schema\Expect::int(),
				'rowFactory' => Schema\Expect::string(),
				'dataTypeParser' => Schema\Expect::string(),
				'dataTypeCache' => Schema\Expect::string(),
				'lazy' => Schema\Expect::bool(true),
				'autowired' => Schema\Expect::bool(),
				'debugger' => Schema\Expect::bool(\class_exists(Tracy\BlueScreen::class)),
				'tracyBluescreenPanelClass' => Schema\Expect::string(PhPgSql\Tracy\BluescreenPanel::class),
				'tracyBarPanelClass' => Schema\Expect::string(PhPgSql\Tracy\BarPanel::class),
				'queryDumper' => Schema\Expect::mixed(), // null|false|string
				'explain' => Schema\Expect::bool(false),
				'notices' => Schema\Expect::bool(false),
				'longQueryTimeMs' => Schema\Expect::anyOf(Schema\Expect::float(), Schema\Expect::int())->castTo('float'),
				'repeatingQueries' => Schema\Expect::bool(false),
				'nonParsedColumns' => Schema\Expect::bool(false),
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
