<?php

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class SyncTest extends Tester\TestCase // @codingStandardsIgnoreLine
{
	use StORMTrait;
	
	public function isSameAsBlueprint(\Migrator\Migrator $toCompareMigrator)
	{
		$connection = $this->getStORMDefault();
		$migrator = new \Migrator\Migrator($connection, $this->getSchemaManager());
		$connection->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		
		
		$sqlDump1 = $migrator->dumpStructure();
		$sqlDump2 = $toCompareMigrator->dumpStructure();
		
		// compare created sql
		Assert::same($sqlDump1, $sqlDump2);
		Assert::true(strlen($sqlDump1) > 0);
		Assert::true(strlen($sqlDump2) > 0);
	}
	
	
	public function testAdd(): void
	{
		
		$connection = $this->getStORMSandbox(3);
		$migrator = new \Migrator\Migrator($connection, $this->getSchemaManager());
		$connection->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
	
		$this->isSameAsBlueprint($migrator);
		
		$sql = $migrator->dumpAlters();
		
		Assert::false(\strlen($sql) > 0);
		
		$connection->query('ALTER TABLE stocks_stock DROP COLUMN name');
		
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		
		$connection->query('ALTER TABLE stocks_stock DROP FOREIGN KEY stocks_stock_sector');
		
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		$connection->query('ALTER TABLE stocks_sector DROP INDEX no_stocks');
		
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
	}
	
	public function testNew(): void
	{
		$connection = $this->getStORMSandbox(3);
		$migrator = new \Migrator\Migrator($connection, $this->getSchemaManager());
		$connection->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);

		$connection->query('ALTER TABLE stocks_stock DROP FOREIGN KEY stocks_stock_sector');
		$connection->query('DROP TABLE stocks_sector');
		
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
	}
	
	public function testModify(): void
	{
		$connection = $this->getStORMSandbox(3);
		$migrator = new \Migrator\Migrator($connection, $this->getSchemaManager());
		$connection->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		
		// column change type
		$connection->query('ALTER TABLE stocks_stock MODIFY COLUMN name TEXT NOT NULL');
		
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		// column change comment
		$connection->query('ALTER TABLE stocks_stock MODIFY COLUMN name VARCHAR(255) COMMENT \'test\'');
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		// column change nullable
		$connection->query('ALTER TABLE stocks_stock MODIFY COLUMN name VARCHAR(255) NULL');
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		// constraint
		$connection->query('ALTER TABLE stocks_stock DROP FOREIGN KEY stocks_stock_sector');
		$connection->query('ALTER TABLE `stocks_stock` ADD CONSTRAINT `stocks_stock_sector` FOREIGN KEY (`fk_sector`) REFERENCES `stocks_sector` (`uuid`) ON DELETE CASCADE ON UPDATE NO ACTION;');
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
		// index
		$connection->query('ALTER TABLE stocks_sector DROP INDEX no_stocks');
		$connection->query('ALTER TABLE `stocks_sector` ADD INDEX `no_stocks` (`no_stocks`,`general`)');
		$sql = $migrator->dumpAlters();
		Assert::true(\strlen($sql) > 0);
		$connection->query($sql);
		$sql = $migrator->dumpAlters();
		Assert::false(\strlen($sql) > 0);
		
	}
	
	public function testDrop(): void
	{
		$connection = $this->getStORMSandbox(3);
		$migrator = new \Migrator\Migrator($connection, $this->getSchemaManager());
		$connection->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		
		// @TODO implement
		// column
		//$connection->query('ALTER TABLE stocks_stock ADD COLUMN test VARCHAR(255) NULL DEFAULT NULL');
		//$sql = $migrator->dumpAlters();
		//echo nl2br($sql);
		
		// @TODO implement
		//echo $migrator->dumpCleanAlters();
		
		// constraint
		// @TODO implement
		
		// index
		// @TODO implement
		
		// trigger
		// @TODO implement
	}
	
	
	
	
}

(new SyncTest())->run();