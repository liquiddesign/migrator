<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;

class Constraint implements ISqlEntity
{
	private const SQL_PROPERTIES = [
		'name',
		'source',
		'sourceKey',
		'target',
		'targetTable',
		'onUpdate',
		'onDelete',
	];

	private \StORM\Meta\Constraint $constraint;
	
	private string $tableName;

	private \StORM\DIConnection $connection;

	private \Migrator\Migrator $migrator;
	
	public function __construct(Migrator $migrator, string $tableName, \StORM\Meta\Constraint $constraint)
	{
		$this->constraint = $constraint;
		$this->tableName = $tableName;
		$this->migrator = $migrator;
		$this->connection = $this->migrator->getConnection();
		
		$this->setDefaults();
	}

	public function getAdd(): string
	{
		$q = $this->connection->getQuoteIdentifierChar();
		
		$name = $this->constraint->getName();
		$source = $this->constraint->getSource();
		$target = $this->constraint->getTarget();
		
		$sourceTable = !\class_exists($source) ? $source : $this->connection->findRepository($source)->getStructure()->getTable()->getName();
		$sourceKey = $this->constraint->getSourceKey();
		$targetTable = !\class_exists($target) ? $target : $this->connection->findRepository($target)->getStructure()->getTable()->getName();
		$targetKey = $this->constraint->getTargetKey();
		$onDelete = $this->constraint->getOnDelete();
		$onUpdate = $this->constraint->getOnUpdate();
		
		$sql = "ALTER TABLE $q$sourceTable$q ADD CONSTRAINT $q$name$q ";
		$sql .= "FOREIGN KEY $q$sourceTable$q ($q$sourceKey$q) REFERENCES $q$targetTable$q ($q$targetKey$q) ON DELETE $onDelete ON UPDATE $onUpdate";
		
		return $sql . ';' . \PHP_EOL;
	}
	
	public function getDrop(): string
	{
		return 'ALTER TABLE '. $this->connection->quoteIdentifier($this->tableName).' DROP FOREIGN KEY '. $this->connection->quoteIdentifier($this->constraint->getName()) . ';' . \PHP_EOL;
	}
	
	public function getChange(string $sourceConstraintName): string
	{
		unset($sourceConstraintName);
		
		return $this->getDrop() . $this->getAdd();
	}
	
	/**
	 * @return string[]
	 */
	public function getSqlProperties(): array
	{
		$props = $this->constraint->jsonSerialize();
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}

	private function setDefaults(): void
	{
		if ($this->constraint->getOnUpdate() === null) {
			$this->constraint->setOnUpdate($this->migrator->getDefaultConstraintActionOnUpdate());
		}
		
		if ($this->constraint->getOnDelete() === null) {
			$this->constraint->setOnDelete($this->migrator->getDefaultConstraintActionOnDelete());
		}
		
		return;
	}
}
