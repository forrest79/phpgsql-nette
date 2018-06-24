<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Nette\DI;

use Nette;
use Forrest79\PhPgSql;
use Tracy;

class Extension extends Nette\DI\CompilerExtension
{
	public $defaults = [
		'connectionClass' => PhPgSql\Fluent\Connection::class,
		'config' => NULL,
		'forceNew' => FALSE,
		'async' => FALSE,
		'asyncWaitSeconds' => NULL,
		'lazy' => TRUE,
		'debugger' => TRUE,
		'autowired' => TRUE,
	];

	/** @var bool */
	private $debugMode;


	public function __construct(bool $debugMode = FALSE)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		$configs = $this->getConfig();
		foreach ($configs as $k => $v) {
			if (is_scalar($v)) {
				$configs = ['default' => $configs];
				break;
			}
		}

		$defaults = $this->defaults;
		foreach ((array) $configs as $name => $config) {
			if (!is_array($config)) {
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

		if (!is_subclass_of($connectionClass, PhPgSql\Db\Connection::class)) {
			throw new \InvalidArgumentException(sprintf('Parameter \'connectionClass\' must extends \'%s\'', PhPgSql\Db\Connection::class));
		}

		$connection = $builder->addDefinition($this->prefix("$name.connection"))
			->setFactory($connectionClass, [$config['config'], $config['forceNew'], $config['async']])
			->setAutowired($config['autowired']);

		if ($config['asyncWaitSeconds'] !== NULL) {
			$connection->addSetup('setConnectAsyncWaitSeconds', [$config['asyncWaitSeconds']]);
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
