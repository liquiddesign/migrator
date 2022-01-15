<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;

class Table implements ISqlEntity
{
	private const SQL_PROPERTIES = [
		'name',
		'engine',
		'collate',
		'comment',
	];

	private \StORM\Connection $connection;

	private \StORM\Meta\Table $table;

	private \Migrator\Migrator $migrator;
	
	public function __construct(Migrator $migrator, \StORM\Meta\Table $table)
	{
		$this->table = $table;
		$this->migrator = $migrator;
		$this->connection = $this->migrator->getConnection();
		
		$this->setDefaults();
	}
	
	public function getMetaTable(): \StORM\Meta\Table
	{
		return $this->table;
	}
	
	/**
	 * @param \StORM\Meta\Column[] $columns
	 * @param bool $ifNotExists
	 */
	public function getAdd(array $columns, bool $ifNotExists = true): string
	{
		$sql = 'CREATE TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . $this->connection->quoteIdentifier($this->table->getName()) . '(' . \PHP_EOL;
		$columnsSql = [];
		$primaryKeys = [];
		
		foreach ($columns as $column) {
			if ($column->isPrimaryKey()) {
				$primaryKeys[] = $this->connection->quoteIdentifier($column->getName());
			}
			
			$sqlColumn = new Column($this->migrator, $this->table->getName(), $column);
			$columnsSql[] = $sqlColumn->getAdd(false);
		}
		
		$sql .= \implode(',' . \PHP_EOL, $columnsSql);
		
		if ($primaryKeys) {
			$sql .= ', PRIMARY KEY(' . \implode(',', $primaryKeys) . ')';
		}
		
		$sql .= ')';
		$sql .= ' ENGINE = ' . $this->table->getEngine();
		$sql .= ', COLLATE = ' . $this->table->getCollate();
		$sql .= ', COMMENT = \'' . $this->table->getComment() . '\'';
		$sql .= ';' . \PHP_EOL;
		
		return $sql;
	}
	
	public function getDrop(): string
	{
		return 'DROP TABLE IF EXISTS ' . $this->connection->quoteIdentifier($this->table->getName()) . ';' . \PHP_EOL;
	}
	
	public function getChange(?string $sourceTableName = null): string
	{
		$sql = '';
		
		if ($sourceTableName && $sourceTableName !== $this->table->getName()) {
			$sql .= 'ALTER TABLE ' . $this->connection->quoteIdentifier($sourceTableName);
			$sql .= ' RENAME ' . $this->connection->quote($this->table->getName()) . ';' . \PHP_EOL;
		}
		
		$sql .= 'ALTER TABLE ' . $this->connection->quoteIdentifier($sourceTableName);
		$sql .= ' ENGINE = ' . $this->connection->quote($this->table->getEngine());
		$sql .= ', COLLATE = ' . $this->connection->quote($this->table->getCollate());
		$sql .= ', COMMENT = \'' . $this->table->getComment() . '\'';
		
		return $sql . ';' . \PHP_EOL;
	}
	
	/**
	 * @return string[]
	 */
	public function getSqlProperties(): array
	{
		$props = $this->table->jsonSerialize();
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}

	private function setDefaults(): void
	{
		if ($this->table->getCollate() === null) {
			$this->table->setCollate($this->migrator->getDefaultCollation());
		}
		
		if ($this->table->getEngine() === null) {
			$this->table->setEngine($this->migrator->getDefaultEngine());
		}
		
		return;
	}
}
