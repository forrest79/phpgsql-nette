<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\DI;

use Forrest79\PhPgSql;
use Nette;
use Tracy;

class Extension extends Nette\DI\CompilerExtension
{
	/** @var array */
	public $defaults = [
		'connectionClass' => PhPgSql\Fluent\Connection::class,
		'config' => NULL,
		'forceNew' => FALSE,
		'async' => FALSE,
		'asyncWaitSeconds' => NULL,
		'defaultRowFactory' => NULL,
		'dataTypeParser' => NULL,
		'dataTypeCache' => NULL,
		'lazy' => TRUE,
		'autowired' => TRUE,
		'debugger' => TRUE,
	];

	/** @var bool */
	private $debugMode;


	public function __construct(bool $debugMode)
	{
		$this->debugMode = $debugMode;
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
		}
	}


	private function setupDatabase(array $config, string $name): void
	{
		$builder = $this->getContainerBuilder();

		$connectionClass = $config['connectionClass'];

		if (!\is_subclass_of($connectionClass, PhPgSql\Db\Connection::class)) {
			throw new \InvalidArgumentException(\sprintf('Parameter \'connectionClass\' must extends \'%s\'', PhPgSql\Db\Connection::class));
		}

		$connection = $builder->addDefinition($this->prefix(\sprintf('%s.connection', $name)))
			->setFactory($connectionClass, [$config['config'], $config['forceNew'], $config['async']])
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
				$connection->addSetup(\sprintf('%s::initializePanel(?, ?)', PhPgSql\Tracy\Panel::class), ['@self', $name]);
			}
		}

		if ($config['lazy'] === FALSE) {
			$connection->addSetup('connect');
		}
	}

}
