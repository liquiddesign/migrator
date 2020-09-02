<?php

trait StORMTrait
{
	/**
	 * @var \Nette\DI\Container
	 */
	protected $container;
	
	public function getContainer(): \Nette\DI\Container
	{
		if (!$this->container) {
			$config = __DIR__ . '/configs/config.neon';
			$tempDir = __DIR__ . '/temp';
			
			$loader = new \Nette\DI\ContainerLoader($tempDir);
			$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config): void {
				$compiler->addExtension('storm', new \StORM\Bridges\StormDI());
				$compiler->loadConfig($config);
			});
			/** @var \Nette\DI\Container $container */
			$this->container = new $class();
		}
		
		return $this->container;
	}
	
	protected function getSchemaManager(): \StORM\SchemaManager
	{
		return $this->getContainer()->getByType(\StORM\SchemaManager::class);
	}
	
	protected function getStORMDefault(): \StORM\DIConnection
	{
		return $this->getContainer()->getService('storm.default');
	}
	
	protected function getStORMSandbox(int $number): \StORM\DIConnection
	{
		return $this->getContainer()->getService('storm.sandbox' . $number);
	}
}