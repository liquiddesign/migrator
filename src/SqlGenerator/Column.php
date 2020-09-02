<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;

class Column implements ISqlEntity
{
	private const SQL_PROPERTIES = [
		'name',
		'type',
		'length',
		'nullable',
		'default',
		'extra',
		'comment',
		'collate',
		'charset',
	];
	
	/**
	 * @var \StORM\Meta\Column
	 */
	private $column;
	
	/**
	 * @var string
	 */
	private $tableName;
	
	/**
	 * @var \StORM\Connection
	 */
	private $connection;
	
	/**
	 * @var \Migrator\Migrator
	 */
	private $migrator;
	
	public function __construct(Migrator $migrator, string $tableName, \StORM\Meta\Column $column)
	{
		$this->column = $column;
		$this->tableName = $tableName;
		$this->migrator = $migrator;
		$this->connection = $migrator->getConnection();
		
		$this->setDefaults();
	}
	
	public function getAdd(bool $withPrefix = true): string
	{
		return $this->getSql(Migrator::ALTER_ADD, $withPrefix);
	}
	
	public function getDrop(): string
	{
		return $this->getSql(Migrator::ALTER_DROP);
	}
	
	public function getChange(string $sourceColumnName): string
	{
		return $this->getSql(Migrator::ALTER_MODIFY, true, $sourceColumnName);
	}
	
	/**
	 * @return string[]
	 * @throws \ReflectionException
	 */
	public function getSqlProperties(): array
	{
		$props = $this->column->jsonSerialize();
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}

	/**
	 * @throws \Exception
	 */
	private function setDefaults(): void
	{
		if ($this->column->getPropertyType()) {
			if ($this->column->getType() === null) {
				$nullable = null;
				$length = null;
				$this->column->setType($this->migrator->getDefaultType($this->column->getPropertyType(), $nullable, $length));
				
				if ($nullable !== null) {
					$this->column->setNullable($nullable);
				}
				
				if ($length !== null) {
					$this->column->setLength($length);
				}
			}
			
			if ($this->column->getLength() === null) {
				if ($this->column->isPrimaryKey() || $this->column->isForeignKey()) {
					$this->column->setLength($this->migrator->getDefaultPrimaryKeyLength($this->column->getType()));
				} else {
					$this->column->setLength($this->migrator->getDefaultLength($this->column->getType()));
				}
			}
		}
		
		if ($this->column->getCollate() === null) {
			$this->column->setCollate($this->migrator->getDefaultCollation());
		}
		
		if ($this->column->getCharset() === null) {
			$this->column->setCharset($this->migrator->getDefaultCharset());
		}
		
		return;
	}
	
	private function getSql(string $alterType, bool $withPrefix = true, ?string $sourceColumnName = null): string
	{
		$table = $this->tableName;
		$q = $this->connection->getQuoteIdentifierChar();
		$name = $this->column->getName();
		$type = $this->column->getType();
		$length = $this->column->getLength() ? '('. $this->column->getLength() .')' : '';
		$nullable = $this->column->isNullable() ? ' NULL' : ' NOT NULL';
		$default = $this->column->getDefault() !== null ? ' DEFAULT ' . $this->column->getDefault() : '';
		$extra = $this->column->isAutoincrement() ? ' AUTO_INCREMENT' : ($this->column->getExtra() ? ' ' . $this->column->getExtra() : '');
		$comment = $this->column->getComment() ? ' COMMENT ' . $this->connection->quote($this->column->getComment()) : '';
		
		$sql = $withPrefix ? "ALTER TABLE $q$table$q $alterType " : '';
		
		if ($sourceColumnName !== null) {
			$sql .= "$q$sourceColumnName$q ";
		}
		
		$sql .= "$q$name$q";
		
		if ($alterType !== Migrator::ALTER_DROP) {
			$sql .= " $type$length$nullable$default$extra$comment";
		}
		
		return $withPrefix ? $sql . ';' . \PHP_EOL : $sql;
	}
}
