<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\DI;

use Forrest79\PhPgSql;
use Nette;
use Tracy;

class Extension extends Nette\DI\CompilerExtension
{
	/** @var array */
	public $defaults = [
		'config' => [],
		'forceNew' => FALSE,
		'async' => FALSE,
		'asyncWaitSeconds' => NULL,
		'defaultRowFactory' => NULL,
		'dataTypeParser' => NULL,
		'dataTypeCache' => NULL,
		'lazy' => TRUE,
		'autowired' => TRUE,
		'debugger' => NULL,
		'logNotices' => FALSE,
	];

	/** @var bool */
	private $debugMode;

	/** @var array */
	private $names = [];


	public function __construct(bool $debugMode)
	{
		$this->debugMode = $debugMode;
		$this->defaults['debugger'] = \class_exists(Tracy\BlueScreen::class);
	}


	/**
	 * @return void
	 */
	public function loadConfiguration()
	{
		$configs = $this->getConfig();
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


	private function setupDatabase(array $config, string $name): void
	{
		$builder = $this->getContainerBuilder();

		$connectionFactory = $this->prefix(\sprintf('%s.connection.factory', $name));

		$builder->addDefinition($connectionFactory)
			->setFactory(PhPgSql\Nette\Connection\FluentConnectionFactory::class);

		$connection = $builder->addDefinition($this->prefix(\sprintf('%s.connection', $name)))
			->setFactory('@' . $connectionFactory . '::create', [$config['config'], $config['forceNew'], $config['async']])
			->setAutowired($config['autowired']);

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

		if ($config['debugger']) {
			$connection->addSetup(\sprintf('@%s::addPanel', Tracy\BlueScreen::class), [
				PhPgSql\Tracy\Panel::class . '::renderException',
			]);
			if ($this->debugMode) {
				$connection->addSetup(\sprintf('%s::initializePanel(?, ?, ?)', PhPgSql\Tracy\Panel::class), ['@self', $name, $config['logNotices']]);
			}
		}

		if ($config['lazy'] === FALSE) {
			$connection->addSetup('connect');
		}
	}


	public function beforeCompile()
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
			if (!\is_subclass_of($connectionType, PhPgSql\Db\Connection::class)) {
				throw new \InvalidArgumentException(\sprintf(
					'Connection factory \'%s\' must return connection that extends \'%s\' in create() method, \'%s\' is returning',
					$connectionFactory,
					PhPgSql\Db\Connection::class,
					$connectionType
				));
			}

			$builder->getDefinition($this->prefix(\sprintf('%s.connection', $name)))->setType($connectionType);
		}
	}

}
