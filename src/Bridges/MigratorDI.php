<?php

declare(strict_types=1);

namespace Migrator\Bridges;

use Migrator\Migrator;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class MigratorDI extends \Nette\DI\CompilerExtension
{
	
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'typeMap' => Expect::arrayOf('string'),
			'lengthMap' => Expect::arrayOf('mixed'),
			'primaryKeyLengthMap' => Expect::arrayOf('int'),
			'primaryKey' => Expect::structure([
				'name' => Expect::string(),
				'propertyType' => Expect::string(),
				'isAutoincrement' => Expect::bool(),
			]),
			'engine' => Expect::string(),
			'constraintActionOnUpdate' => Expect::string(),
			'constraintActionOnDelete' => Expect::string(),
		]);
	}
	
	public function loadConfiguration(): void
	{
		/** @var \stdClass $configuration */
		$configuration = $this->getConfig();
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		$schemaManager = $builder->addDefinition($this->prefix('migrator'))->setType(Migrator::class)->setAutowired(true);
		
		if ($configuration->typeMap) {
			//$schemaManager->addSetup('setCustomAnnotations', [$configuration->schema->customAnnotations]);
		}
		
		return;
	}
}
