<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\DI;

use Forrest79\PhPgSql;
use Nette;
use Nette\Schema;
use Tracy;

class Extension extends Nette\DI\CompilerExtension
{
	/** @var array<string, mixed> */
	public $defaults = [
		'config' => [],
		'forceNew' => FALSE,
		'async' => FALSE,
		'errorVerbosity' => NULL,
		'asyncWaitSeconds' => NULL,
		'defaultRowFactory' => NULL,
		'dataTypeParser' => NULL,
		'dataTypeCache' => NULL,
		'lazy' => TRUE,
		'autowired' => TRUE,
		'debugger' => NULL,
		'explain' => FALSE,
		'notices' => FALSE,
		'longQueryTime' => NULL,
		'repeatingQueries' => FALSE,
		'nonParsedColumns' => FALSE,
	];

	/** @var bool */
	private $debugMode;

	/** @var array<string> */
	private $names = [];


	public function __construct(bool $debugMode)
	{
		$this->debugMode = $debugMode;
		$this->defaults['debugger'] = \class_exists(Tracy\BlueScreen::class);
	}


	public function loadConfiguration(): void
	{
		$configs = (array) $this->getConfig();
		if (\method_exists(Nette\DI\CompilerExtension::class, 'getConfigSchema')) {
			$this->loadConfiguration30($configs);
		} else {
			$this->loadConfiguration24($configs);
		}
	}


	/**
	 * @param array<string, mixed> $configs
	 */
	private function loadConfiguration24(array $configs): void
	{
		foreach ($configs as $values) {
			if (\is_scalar($values)) {
				$configs = ['default' => $configs];
				break;
			}
		}

		$defaults = $this->defaults;
		foreach ($configs as $name => $config) {
			if (!\is_array($config)) {
				continue;
			}
			$config = $this->validateConfig($defaults, $config, $this->prefix($name));
			$defaults['autowired'] = FALSE;
			$this->setupDatabase($config, $name);

			$this->names[] = $name;
		}
	}


	/**
	 * @param array<string, mixed> $configs @todo \stdClass after Nette 2.4 will be dropped
	 */
	private function loadConfiguration30(array $configs): void
	{
		$autowired = TRUE;
		foreach ($configs as $name => $config) {
			$config->autowired = $config->autowired ?? $autowired;
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

		if ($config['defaultRowFactory'] !== NULL) {
			$connection->addSetup('setDefaultRowFactory', [$config['defaultRowFactory']]);
		}

		if ($config['dataTypeParser'] !== NULL) {
			$connection->addSetup('setDataTypeParser', [$config['dataTypeParser']]);
		}

		if ($config['dataTypeCache'] !== NULL) {
			$connection->addSetup('setDataTypeCache', [$config['dataTypeCache']]);
		}

		if ($config['debugger'] === TRUE) {
			$connection->addSetup(\sprintf('@%s::addPanel', Tracy\BlueScreen::class), [
				PhPgSql\Tracy\Panel::class . '::renderException',
			]);
			if ($this->debugMode) {
				$connection->addSetup(\sprintf('%s::initializePanel(?, ?, ?, ?, ?, ?, ?)', PhPgSql\Tracy\Panel::class), [
					'@self',
					$name,
					$config['explain'],
					$config['notices'],
					$config['longQueryTime'],
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
					PhPgSql\Nette\Connection\ConnectionCreator::class
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
					$connectionType
				));
			}

			$service = $builder->getDefinition($this->prefix(\sprintf('%s.connection', $name)));
			\assert($service instanceof Nette\DI\ServiceDefinition);
			$service->setType($connectionType);
		}
	}


	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::arrayOf(
			Schema\Expect::structure([
				'config' => Schema\Expect::array([]),
				'forceNew' => Schema\Expect::bool(FALSE),
				'async' => Schema\Expect::bool(FALSE),
				'errorVerbosity' => Schema\Expect::int(),
				'asyncWaitSeconds' => Schema\Expect::int(),
				'defaultRowFactory' => Schema\Expect::string(),
				'dataTypeParser' => Schema\Expect::string(),
				'dataTypeCache' => Schema\Expect::string(),
				'lazy' => Schema\Expect::bool(TRUE),
				'autowired' => Schema\Expect::bool(),
				'debugger' => Schema\Expect::bool(\class_exists(Tracy\BlueScreen::class)),
				'explain' => Schema\Expect::bool(FALSE),
				'notices' => Schema\Expect::bool(FALSE),
				'longQueryTime' => Schema\Expect::float(),
				'repeatingQueries' => Schema\Expect::bool(FALSE),
				'nonParsedColumns' => Schema\Expect::bool(FALSE),
			])
		)->before(static function ($config) {
			foreach ($config as $values) {
				if (\is_scalar($values)) {
					$config = ['default' => $config];
					break;
				}
			}
			return $config;
		});
	}

}
