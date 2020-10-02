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
				'varchar' => 255,
				'tinyint' => 1,
			]),
			'primaryKeyLengthMap' => Expect::arrayOf('int')->default([
				'int' => 11,
				'varchar' => 32,
			]),
			'primaryKey' => Expect::structure([
				'name' => Expect::string('uuid'),
				'propertyType' => Expect::string('string'),
				'autoincrement' => Expect::bool(false),
			]),
			'engine' => Expect::string('innoDB'),
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
		$schemaManager = $builder->addDefinition($this->prefix('migrator'))->setType(Migrator::class)->setAutowired(true);
		
		$schemaManager->addSetup('setDefaultEngine', [$configuration->engine]);
		$schemaManager->addSetup('setDefaultTypeMap', [$configuration->typeMap]);
		$schemaManager->addSetup('setDefaultLengthMap', [$configuration->lengthMap]);
		$schemaManager->addSetup('setDefaultPrimaryKeyLengthMap', [$configuration->primaryKeyLengthMap]);
		$schemaManager->addSetup('setDefaultPrimaryKeyConfiguration', [(array) $configuration->primaryKey]);
		$schemaManager->addSetup('setDefaultConstraintActions', [$configuration->constraintActionOnUpdate, $configuration->constraintActionOnDelete]);
		
		return;
	}
}
