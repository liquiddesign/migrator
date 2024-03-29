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
			'typeMap' => Expect::arrayOf('string')->default([
				'int' => 'int',
				'string' => 'varchar',
				'bool' => 'tinyint',
				'float' => 'double',
			]),
			'lengthMap' => Expect::arrayOf('mixed')->default([
				'int' => 11,
				'bigint' => 20,
				'varchar' => 255,
				'tinyint' => 1,
			]),
			'primaryKeyLengthMap' => Expect::arrayOf('int')->default([
				'int' => 11,
				'bigint' => 20,
				'varchar' => 32,
			]),
			'primaryKey' => Expect::structure([
				'name' => Expect::string('uuid'),
				'propertyType' => Expect::string('string'),
				'autoincrement' => Expect::bool(false),
			]),
			'debug' => Expect::bool(false),
			'engine' => Expect::string('InnoDB'),
			'constraintActionOnUpdate' => Expect::string('NO ACTION'),
			'constraintActionOnDelete' => Expect::string('NO ACTION'),
		]);
	}
	
	public function loadConfiguration(): void
	{
		/** @var \stdClass $configuration */
		$configuration = $this->getConfig();
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $schemaManager */
		$schemaManager = $builder->addDefinition($this->prefix('migrator'));
		$schemaManager->setType(Migrator::class)->setAutowired(true);
		
		$schemaManager->addSetup('setDebug', [$configuration->debug]);
		$schemaManager->addSetup('setDefaultEngine', [$configuration->engine]);
		$schemaManager->addSetup('setDefaultTypeMap', [$configuration->typeMap]);
		$schemaManager->addSetup('setDefaultLengthMap', [$configuration->lengthMap]);
		$schemaManager->addSetup('setDefaultPrimaryKeyLengthMap', [$configuration->primaryKeyLengthMap]);
		$schemaManager->addSetup('setDefaultPrimaryKeyConfiguration', [(array) $configuration->primaryKey]);
		$schemaManager->addSetup('setDefaultConstraintActions', [$configuration->constraintActionOnUpdate, $configuration->constraintActionOnDelete]);
	}
}
