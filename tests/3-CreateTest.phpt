<?php

use StORM\Connection;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class CreateTest extends Tester\TestCase // @codingStandardsIgnoreLine
{
	use StORMTrait;
	
	public function testCreate(): void
	{
		$schemaManager = $this->getSchemaManager();
		$connection1 = $this->getStORMSandbox(1);
		$connection2 = $this->getStORMSandbox(2);
		
		$migrator1 = new \Migrator\Migrator($connection1, $schemaManager);
		$migrator2 = new \Migrator\Migrator($connection2, $schemaManager);
		
		$connection1->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		
		
		
		$connection1->query($migrator1->dumpStructure());
		$connection2->query($migrator1->dumpRealStructure());
		
		$sqlDump1 = $migrator1->dumpRealStructure();
		$sqlDump2 = $migrator2->dumpRealStructure();
		
		// compare created sql
		Assert::same($sqlDump1, $sqlDump2);
		Assert::true(strlen($sqlDump1) > 0);
		Assert::true(strlen($sqlDump2) > 0);
	}

	
}

(new CreateTest())->run();