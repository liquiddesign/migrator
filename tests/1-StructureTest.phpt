<?php

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class StructureTest extends Tester\TestCase // @codingStandardsIgnoreLine
{
	use StORMTrait;
	
	private const TABLES = [
		'stocks_alert',
		'stocks_industry',
		'stocks_sector',
		'stocks_stock',
		'stocks_stock_nxn_stocks_tag',
		'stocks_tag',
		'stocks_type',
		];
	
	private const TEST_TABLE = 'stocks_stock';
	
	public function testList(): void
	{
		$connection = $this->getStORMDefault();
		$schemaManager = $this->getSchemaManager();
		
		$migrator = new \Migrator\Migrator($connection, $schemaManager);
		$stocks = $connection->findRepository(\DB\Stock::class);
		
		
		$tables = $migrator->getTableNames();
		
		Assert::equal($tables, self::TABLES);
		
		// compare tables
		$realTable = $migrator->getTable(self::TEST_TABLE);
		$entityTable =  $stocks->getStructure()->getTable();
		Assert::same($realTable->getName(), $entityTable->getName());
		
		// compare columns
		$realColumns = \array_keys($migrator->getColumns('stocks_stock'));
		$entityColumns = [];
		foreach ($stocks->getStructure()->getColumns() as $columns) {
			$entityColumns[] = $columns->getName();
		}
		Assert::equal($realColumns, $entityColumns);
		
		// compare keys
		$realPK = $migrator->getPrimaryColumn('stocks_stock');
		$entityPK = $stocks->getStructure()->getPK();
		Assert::same($realPK->getName(), $entityPK->getName());
		
		// compare constraints
		$realConstraints = \array_keys($migrator->getConstraints('stocks_stock'));
		$entityConstraints = [];
		foreach ($stocks->getStructure()->getConstraints() as $constraint) {
			$entityConstraints[] = $constraint->getName();
		}
		Assert::equal(\sort($realConstraints), \sort($entityConstraints));
		
		// compare indexes
		$realIndexes = \array_keys($migrator->getIndexes('stocks_stock'));
		$entityIndexes = [];
		foreach ($stocks->getStructure()->getIndexes() as $index) {
			$entityIndexes[] = $index->getName();
		}
		Assert::equal(\sort($realIndexes), \sort($entityIndexes));
		
		// compare triggers
		$realTriggers = \array_keys($migrator->getTriggers('stocks_stock'));
		$entityTriggers = [];
		foreach ($stocks->getStructure()->getTriggers() as $trigger) {
			$entityTriggers[] = $trigger->getName();
		}
		Assert::equal(\sort($realTriggers), \sort($entityTriggers));
	}
}

(new StructureTest())->run();