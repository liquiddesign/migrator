<?php

use StORM\Connection;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class DefaultsTest extends Tester\TestCase // @codingStandardsIgnoreLine
{
	use StORMTrait;

	public function testDefaults(): void
	{
		$connection = $this->getStORMDefault();
		$schemaManager = $this->getSchemaManager();
		
		$migrator = new \Migrator\Migrator($connection, $schemaManager);
		$stocks = $connection->findRepository(\DB\Stock::class);
		
		$migrator->setDefaultCollation('utf8mb4_czech_ci');
		$migrator->setDefaultEngine('MyISAM');
		
		// default table properties
		$sqlTable = new \Migrator\SqlGenerator\Table($migrator, $stocks->getStructure()->getTable());
		$properties = $sqlTable->getSqlProperties();
		Assert::same($migrator->getDefaultCollation(),$properties['collate']);
		Assert::same($migrator->getDefaultEngine(),$properties['engine']);
	
		
		$migrator->setDefaultCharset('utf8mb4');
		$migrator->setDefaultPrimaryKeyLengthMap([
			'int' => 11,
			'varchar' => 32,
		]);
		$migrator->setDefaultTypeMap([
			'int' => 'int',
			'string' => 'varchar',
			'bool' => 'tinyint',
			'float' => 'double',
		]);
		$migrator->setDefaultLengthMap([
			'int' => 11,
			'varchar' => 255,
			'tinyint' => 1,
		]);
		
		foreach ($stocks->getStructure()->getColumns() as $column) {
			
			$sqlColumn = new \Migrator\SqlGenerator\Column($migrator, 'stocks_stock', $column);
			$properties = $sqlColumn->getSqlProperties();
			if ($column->isForeignKey() || $column->isPrimaryKey()) {
				if ($column->getPropertyType() === 'string') {
					Assert::same('varchar', $properties['type']);
					Assert::same(32, (int) $properties['length']);
				}
				
				if ($column->getPropertyType() === 'int') {
					Assert::same('varchar', $properties['type']);
					Assert::same(32, (int) $properties['length']);
				}
			} else {
				if ($column->getPropertyType() === 'string') {
					Assert::same('varchar', $properties['type']);
					Assert::same(255, (int) $properties['length']);
				}
				
				if ($column->getPropertyType() === 'int') {
					Assert::same('int', $properties['type']);
					Assert::same(11, (int) $properties['length']);
				}
				
				if ($column->getPropertyType() === 'bool') {
					Assert::same('tinyint', $properties['type']);
					Assert::same(1, (int) $properties['length']);
				}
			}
			Assert::same('utf8mb4_czech_ci', $properties['collate']);
			Assert::same('utf8mb4', $properties['charset']);
		}
		
		$migrator->setDefaultConstraintActions('NO ACTION', 'NO ACTION');
		foreach ($stocks->getStructure()->getConstraints() as $constraint) {
			$sqlColumn = new \Migrator\SqlGenerator\Constraint($migrator, 'stocks_stock', $constraint);
			$properties = $sqlColumn->getSqlProperties();
			Assert::same('NO ACTION', $properties['onDelete']);
			Assert::same('NO ACTION', $properties['onUpdate']);
		}
	}
}

(new DefaultsTest())->run();