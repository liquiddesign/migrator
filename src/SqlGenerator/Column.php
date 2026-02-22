<?php

namespace Migrator\SqlGenerator;

use Migrator\Migrator;
use Nette\Utils\Arrays;

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
		'autoincrement',
	];
	
	private \StORM\Meta\Column $column;
	
	private string $tableName;
	
	private \StORM\Connection $connection;
	
	private \Migrator\Migrator $migrator;
	
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
	 * @return array<string>
	 */
	public function getSqlProperties(): array
	{
		$props = $this->column->jsonSerialize();
		$props['length'] = (string) $props['length'];
		$props['autoincrement'] = (int) $props['autoincrement'];
		
		if (Arrays::contains(['int', 'tinyint', 'bigint'], $props['type']) && \version_compare($this->migrator->getSqlVersion(), '8.0.19', '>=')) {
			$props['length'] = '';
		}
		
		return \array_intersect_key($props, \array_flip(self::SQL_PROPERTIES));
	}
	
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
		
		$isJsonType = $this->column->getType() === 'json';

		if ($this->column->getCollate() === null && !$isJsonType) {
			$this->column->setCollate($this->migrator->getDefaultCollation());
		}

		if ($this->column->getCharset() === null && !$isJsonType) {
			$this->column->setCharset($this->migrator->getDefaultCharset());
		}
		
		if ($this->column->getPropertyName() !== null && \class_exists($this->column->getEntityClass())) {
			$defaultValue = (new \ReflectionClass($this->column->getEntityClass()))->getDefaultProperties()[$this->column->getPropertyName()] ?? null;
			
			if ($defaultValue !== null) {
				if (\is_bool($defaultValue)) {
					$defaultValue = (int) $defaultValue;
				}
				
				$this->column->setDefault((string) $defaultValue);
			}
		}
		
		return;
	}
	
	private function getSql(string $alterType, bool $withPrefix = true, ?string $sourceColumnName = null): string
	{
		$table = $this->tableName;
		$q = $this->connection->getQuoteIdentifierChar();
		$name = $this->column->getName();
		$type = $this->column->getType();
		$length = $this->column->getLength() ? '(' . $this->column->getLength() . ')' : '';
		$nullable = $this->column->isNullable() ? ' NULL' : ' NOT NULL';
		$noWrap = (\is_numeric($this->column->getDefault()) && $type !== 'varchar') || $this->column->getDefault() === 'CURRENT_TIMESTAMP';
		$default = $this->column->getDefault() !== null ? ' DEFAULT ' . ($noWrap ? $this->column->getDefault() : "'" . $this->column->getDefault() . "'") : '';
		$extra = $this->column->isAutoincrement() ? ' ' . ($this->column->isPrimaryKey() ? '' : 'UNIQUE ') . 'AUTO_INCREMENT' : ($this->column->getExtra() ? ' ' . $this->column->getExtra() : '');
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
