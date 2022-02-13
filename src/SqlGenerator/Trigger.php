<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;

class Trigger implements ISqlEntity
{
	private const SQL_PROPERTIES = [
		'name',
		'manipulation',
		'statement',
		'timing',
	];
	
	private \StORM\Meta\Trigger $trigger;
	
	private \StORM\Connection $connection;
	
	private string $tableName;

	private \Migrator\Migrator $migrator;
	
	public function __construct(Migrator $migrator, string $tableName, \StORM\Meta\Trigger $trigger)
	{
		$this->trigger = $trigger;
		$this->tableName = $tableName;
		$this->migrator = $migrator;
		$this->connection = $this->migrator->getConnection();
		
		$this->setDefaults();
	}
	
	public function getAdd(): string
	{
		$q = $this->connection->getQuoteIdentifierChar();
		
		$name = $this->trigger->getName();
		$table = $this->tableName;
		$timing = $this->trigger->getTiming();
		$statement = $this->trigger->getStatement();
		$manipulation = $this->trigger->getManipulation();
		
		return "CREATE TRIGGER $q$name$q $timing $manipulation ON $q$table$q FOR EACH ROW $statement" . ';' . \PHP_EOL;
	}
	
	public function getDrop(?string $triggerName = null): string
	{
		return 'DROP TRIGGER ' . $this->connection->quoteIdentifier($triggerName ?: $this->trigger->getName()) . ';' . \PHP_EOL;
	}
	
	public function getChange(string $sourceTriggerName): string
	{
		return $this->getDrop($sourceTriggerName) . $this->getAdd();
	}
	
	/**
	 * @return array<string>
	 */
	public function getSqlProperties(): array
	{
		$props = $this->trigger->jsonSerialize();
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}
	
	private function setDefaults(): void
	{
		return;
	}
}
