<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;

class Index implements ISqlEntity
{
	private const SQL_PROPERTIES = [
		'name',
		'columns',
		'unique',
	];

	private \StORM\Meta\Index $index;

	private string $tableName;

	private \StORM\Connection $connection;
	
	private \Migrator\Migrator $migrator;
	
	public function __construct(Migrator $migrator, string $tableName, \StORM\Meta\Index $index)
	{
		$this->index = $index;
		$this->tableName = $tableName;
		$this->migrator = $migrator;
		$this->connection = $this->migrator->getConnection();
		
		$this->setDefaults();
	}
	
	public function getAdd(): string
	{
		$q = $this->connection->getQuoteIdentifierChar();
		
		$table = $this->tableName;
		$name = $this->index->getName();
		$key = $this->index->isUnique() ? 'UNIQUE INDEX' : 'INDEX';
		$columns = \implode(',', \array_map([$this->connection, 'quoteIdentifier'], $this->index->getColumns()));
		
		return "ALTER TABLE $q$table$q ADD $key $q$name$q ($columns);" . \PHP_EOL;
	}
	
	public function getDrop(): string
	{
		return 'ALTER TABLE ' . $this->connection->quoteIdentifier($this->tableName) . ' DROP KEY ' . $this->connection->quoteIdentifier($this->index->getName()) . ';' . \PHP_EOL;
	}
	
	public function getChange(string $sourceIndexName): string
	{
		unset($sourceIndexName);
		
		return $this->getDrop() . $this->getAdd();
	}
	
	/**
	 * @return string[]
	 */
	public function getSqlProperties(): array
	{
		$props = $this->index->jsonSerialize();
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}
	
	private function setDefaults(): void
	{
		//$this->index->setUnique(false);
	}
}
