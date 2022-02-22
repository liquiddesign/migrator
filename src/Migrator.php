<?php

namespace Migrator;

use Migrator\SqlGenerator\ISqlEntity;
use Nette\Caching\Cache;
use Nette\Caching\Storages\DevNullStorage;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\DIConnection;
use StORM\Helpers;
use StORM\Meta\Column;
use StORM\Meta\Constraint;
use StORM\Meta\Index;
use StORM\Meta\RelationNxN;
use StORM\Meta\Structure;
use StORM\Meta\Table;
use StORM\Meta\Trigger;
use StORM\SchemaManager;

class Migrator
{
	public const ALTER_DROP = 'DROP';
	public const ALTER_MODIFY = 'CHANGE';
	public const ALTER_ADD = 'ADD';
	public const NULL = 'null';
	
	/**
	 * @var array<callable>
	 */
	public array $onCompareFail = [];
	
	private \StORM\DIConnection $connection;
	
	private string $defaultCharset;
	
	private string $defaultCollation;
	
	/**
	 * @var array<string>
	 */
	private array $defaultTypeMap = [
		'int' => 'int',
		'string' => 'varchar',
		'bool' => 'tinyint',
		'float' => 'double',
	];
	
	/**
	 * @var array<string>|array<int>
	 */
	private array $defaultLengthMap = [
		'int' => 11,
		'bigint' => 20,
		'varchar' => 255,
		'tinyint' => 1,
	];
	
	/**
	 * @var array<string>|array<int>
	 */
	private array $defaultPrimaryKeyLengthMap = [
		'int' => 11,
		'bigint' => 20,
		'varchar' => 32,
	];
	
	private string $defaultEngine = 'InnoDB';
	
	private string $defaultConstraintActionOnUpdate = 'NO ACTION';
	
	private string $defaultConstraintActionOnDelete = 'NO ACTION';
	
	private \StORM\SchemaManager $schemaManager;
	
	/**
	 * @var array<mixed>
	 */
	private array $defaultPrimaryKeyConfiguration = [
		'name' => 'uuid',
		'propertyType' => 'string',
	];
	
	private string $sqlDefaultAction;
	
	public function __construct(DIConnection $connection, SchemaManager $schemaManager)
	{
		$this->connection = $connection;
		$this->schemaManager = $schemaManager;
		$this->defaultCharset = (string) $connection->rows()->firstValue("CHARSET('')");
		
		if ($this->defaultCharset === 'utf8mb3') {
			$this->defaultCharset = 'utf8';
		}
		
		$this->defaultCollation = (string) $connection->rows()->firstValue("COLLATION('')");
	}
	
	public function getConnection(): DIConnection
	{
		return $this->connection;
	}
	
	/**
	 * @return array<string>
	 */
	public function getDefaultLengthMap(): array
	{
		return $this->defaultLengthMap;
	}
	
	/**
	 * @param array<string> $defaultLengthMap
	 */
	public function setDefaultPrimaryKeyLengthMap(array $defaultLengthMap): void
	{
		$this->defaultPrimaryKeyLengthMap = $defaultLengthMap;
	}
	
	/**
	 * @param string $sqlType
	 * @throws \Exception
	 */
	public function getDefaultPrimaryKeyLength(string $sqlType): string
	{
		if (isset($this->defaultPrimaryKeyLengthMap['int']) && \version_compare($this->getSqlVersion(), '8.0.19', '>=')) {
			unset($this->defaultPrimaryKeyLengthMap['int']);
			unset($this->defaultPrimaryKeyLengthMap['bigint']);
			unset($this->defaultPrimaryKeyLengthMap['tinyint']);
		}
		
		return $this->defaultPrimaryKeyLengthMap[$sqlType] ?? '';
	}
	
	/**
	 * @param array<string> $defaultLengthMap
	 */
	public function setDefaultLengthMap(array $defaultLengthMap): void
	{
		$this->defaultLengthMap = $defaultLengthMap;
	}
	
	/**
	 * @param string $sqlType
	 * @throws \Exception
	 */
	public function getDefaultLength(string $sqlType): ?string
	{
		if (isset($this->defaultLengthMap['int']) && \version_compare($this->getSqlVersion(), '8.0.19', '>=')) {
			unset($this->defaultLengthMap['int']);
		}
		
		return $this->defaultLengthMap[$sqlType] ?? null;
	}
	
	/**
	 * @param string $type
	 * @param bool|null $nullable
	 * @param string|null $length
	 * @throws \Exception
	 */
	public function getDefaultType(string $type, ?bool &$nullable, ?string &$length): string
	{
		$types = \explode('|', $type);
		$type = \array_shift($types);
		$enum = [];
		
		foreach ($types as $subType) {
			if ($subType === self::NULL) {
				$nullable = true;
				
				continue;
			}
			
			$matches = [];
			\preg_match("/'(.*?)'/", $subType, $matches);
			
			if ($matches) {
				$enum[] = $matches[0];
			}
			
			continue;
		}
		
		if ($enum) {
			$length = \implode(',', $enum);
			
			return 'ENUM';
		}
		
		if (!isset($this->defaultTypeMap[$type])) {
			return $type;
		}
		
		return $this->defaultTypeMap[$type];
	}
	
	/**
	 * @param array<string> $defaultTypeMap
	 */
	public function setDefaultTypeMap(array $defaultTypeMap): void
	{
		$this->defaultTypeMap = $defaultTypeMap;
	}
	
	/**
	 * @return array<string>
	 */
	public function getDefaultTypeMap(): array
	{
		return $this->defaultTypeMap;
	}
	
	/**
	 * @param string $tableName
	 * @param bool $onlyPrimary
	 * @return array<\StORM\Meta\Column>
	 */
	public function getColumns(string $tableName, bool $onlyPrimary = false): array
	{
		$select = [
			'name' => 'this.COLUMN_NAME',
			'nullable' => "IF(this.IS_NULLABLE = 'NO',0,1)",
			'type' => 'this.DATA_TYPE',
			'columnType' => 'this.COLUMN_TYPE',
			$this->connection->quoteIdentifier('default') => 'this.COLUMN_DEFAULT',
			'comment' => 'this.COLUMN_COMMENT',
			$this->connection->quoteIdentifier('primaryKey') => "IF(this.COLUMN_KEY = 'PRI',1,0)",
			$this->connection->quoteIdentifier('autoincrement') => "IF(this.EXTRA = 'AUTO_INCREMENT',1,0)",
			$this->connection->quoteIdentifier('extra') => "IF(this.EXTRA IN ('AUTO_INCREMENT'),'',this.EXTRA)",
			$this->connection->quoteIdentifier('collate') => 'this.COLLATION_NAME',
			$this->connection->quoteIdentifier('charset') => 'this.CHARACTER_SET_NAME',
		];
		
		$from = ['this' => 'INFORMATION_SCHEMA.COLUMNS'];
		$dbName = $this->connection->getDatabaseName();
		
		$rows = $this->connection->rows($from, $select)->where('this.TABLE_SCHEMA', $dbName)->where('this.TABLE_NAME', $tableName);
		
		if ($onlyPrimary) {
			$rows->where('this.COLUMN_KEY', 'PRI');
		}
		
		$columns = [];
		
		/** @var \StdClass $data */
		foreach ($rows as $data) {
			$column = new Column($tableName, $data->name);
			$column->setPrimaryKey((bool) $data->primaryKey);
			$data->nullable = (bool) $data->nullable;
			$length = null;
			\preg_match('/.+\((.*?)\)/', $data->columnType, $length);
			unset($data->columnType, $data->primaryKey);
			
			$data->length = $length[1] ?? null;
			$data->autoincrement = (bool) $data->autoincrement;
			
			$data->extra = Strings::trim(Strings::replace($data->extra, '/DEFAULT_GENERATED/', ''));
			
			$column->loadFromArray(Helpers::toArrayRecursive($data));
			
			$columns[$data->name] = $column;
		}
		
		return $columns;
	}
	
	public function getPrimaryColumn(string $tableName): ?Column
	{
		$columns = $this->getColumns($tableName, true);
		
		return \reset($columns) ?: null;
	}
	
	/**
	 * @param string $tableName
	 * @return array<\StORM\Meta\Constraint>
	 */
	public function getConstraints(string $tableName): array
	{
		$rows = [];
		$pn = '(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';
		$onActions = 'RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT';
		$createTable = $this->getConnection()->query("SHOW CREATE TABLE $tableName")->fetchColumn(1);
		$defaultAction = $this->getSqlDefaultAction();
		
		if ($createTable) {
			$pattern = "~CONSTRAINT ($pn) FOREIGN KEY ?\\(((?:$pn,? ?)+)\\) REFERENCES ($pn)(?:\\.($pn))? \\(((?:$pn,? ?)+)\\)(?: ON DELETE ($onActions))?(?: ON UPDATE ($onActions))?~";
			\preg_match_all($pattern, $createTable, $matches, \PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				\preg_match_all("~$pn~", $match[2], $source);
				\preg_match_all("~$pn~", $match[5], $target);
				
				$rows[] = (object) [
					'name' => Strings::substring($match[1], 1, -1),
					'source' => $tableName,
					'target' => Strings::substring($match[4] !== '' ? $match[4] : $match[3], 1, -1),
					'sourceKey' => Strings::substring($source[0][0], 1, -1),
					'targetKey' => Strings::substring($target[0][0], 1, -1),
					'onDelete' => $match[6] ?? $defaultAction,
					'onUpdate' => $match[7] ?? $defaultAction,
				];
			}
		}
		
		$constraints = [];
		
		/** @var \StdClass $data */
		foreach ($rows as $data) {
			$constraint = new Constraint($tableName, $data->name);
			$constraint->loadFromArray(Helpers::toArrayRecursive($data));
			$constraints[$data->name] = $constraint;
		}
		
		return $constraints;
	}
	
	/**
	 * @param string $tableName
	 * @param bool $skipPrimary
	 * @return array<\StORM\Meta\Index>
	 */
	public function getIndexes(string $tableName, bool $skipPrimary = true): array
	{
		$select = [
			'name' => 'this.INDEX_NAME',
			'columns' => 'GROUP_CONCAT(this.COLUMN_NAME ORDER BY this.SEQ_IN_INDEX ASC)',
			$this->connection->quoteIdentifier('unique') => 'IF(this.NON_UNIQUE,0,1)',
		];
		
		$from = ['this' => 'INFORMATION_SCHEMA.STATISTICS'];
		$dbName = $this->connection->getDatabaseName();
		
		$rows = $this->connection->rows($from, $select)->where('this.TABLE_SCHEMA', $dbName)->where('this.TABLE_NAME', $tableName);
		
		if ($skipPrimary) {
			$rows->whereNot('this.INDEX_NAME', 'PRIMARY');
		}
		
		$rows->setGroupBy(['this.INDEX_NAME']);
		
		
		$indexes = [];
		
		/** @var \StdClass $data */
		foreach ($rows as $data) {
			$index = new Index($tableName);
			$data->unique = (bool) $data->unique;
			$data->columns = \explode(',', $data->columns);
			$index->loadFromArray(Helpers::toArrayRecursive($data));
			$indexes[$data->name] = $index;
		}
		
		return $indexes;
	}
	
	/**
	 * @param string $tableName
	 * @return array<\StORM\Meta\Trigger>
	 */
	public function getTriggers(string $tableName): array
	{
		$select = [
			'name' => 'this.TRIGGER_NAME',
			'manipulation' => 'this.EVENT_MANIPULATION',
			'timing' => 'this.ACTION_TIMING',
			'statement' => 'this.ACTION_STATEMENT',
		];
		
		$from = ['this' => 'INFORMATION_SCHEMA.TRIGGERS'];
		$dbName = $this->connection->getDatabaseName();
		
		$rows = $this->connection->rows($from, $select)->where('this.TRIGGER_SCHEMA', $dbName)->where('this.EVENT_OBJECT_TABLE', $tableName);
		$triggers = [];
		
		/** @var \StdClass $data */
		foreach ($rows as $data) {
			$trigger = new Trigger($tableName);
			$trigger->loadFromArray(Helpers::toArrayRecursive($data));
			$triggers[$data->name] = $trigger;
		}
		
		return $triggers;
	}
	
	/**
	 * @return array<string>
	 */
	public function getTableNames(): array
	{
		$dbName = $this->connection->getDatabaseName();
		$from = ['this' => 'INFORMATION_SCHEMA.TABLES'];
		
		return $this->connection->rows($from, ['this.TABLE_NAME'])->where('this.TABLE_SCHEMA', $dbName)->toArrayOf('TABLE_NAME');
	}
	
	/**
	 * @return array<\StORM\Meta\Table>
	 */
	public function getTables(): array
	{
		$select = [
			'name' => 'this.TABLE_NAME',
			$this->connection->quoteIdentifier('collate') => 'this.TABLE_COLLATION',
			'engine' => 'this.ENGINE',
			'comment' => 'this.TABLE_COMMENT',
		];
		$from = ['this' => 'INFORMATION_SCHEMA.TABLES'];
		$dbName = $this->connection->getDatabaseName();
		
		$rows = $this->connection->rows($from, $select)->where('this.TABLE_SCHEMA', $dbName);
		
		$tables = [];
		
		/** @var \StdClass $data */
		foreach ($rows as $data) {
			$table = new Table($data->name);
			$table->loadFromArray(Helpers::toArrayRecursive($data));
			$tables[$data->name] = $table;
		}
		
		return $tables;
	}
	
	public function getTable(string $tableName): ?Table
	{
		$select = [
			'name' => 'this.TABLE_NAME',
			$this->connection->quoteIdentifier('collate') => 'this.TABLE_COLLATION',
			'engine' => 'this.ENGINE',
			'comment' => 'this.TABLE_COMMENT',
		];
		$from = ['this' => 'INFORMATION_SCHEMA.TABLES'];
		$dbName = $this->connection->getDatabaseName();
		
		$data = $this->connection->rows($from, $select)->where('this.TABLE_SCHEMA', $dbName)->where('this.TABLE_NAME', $tableName)->first();
		
		if (!$data) {
			return null;
		}
		
		$table = new Table($tableName);
		$table->loadFromArray(Helpers::toArrayRecursive($data));
		
		return $table;
	}
	
	public function getDefaultCharset(): string
	{
		return $this->defaultCharset;
	}
	
	public function setDefaultCharset(string $defaultCharset): void
	{
		$this->defaultCharset = $defaultCharset;
	}
	
	public function setDefaultEngine(string $defaultEngine): void
	{
		$this->defaultEngine = $defaultEngine;
	}
	
	public function getDefaultEngine(): string
	{
		return $this->defaultEngine;
	}
	
	public function getDefaultCollation(): string
	{
		return $this->defaultCollation;
	}
	
	public function setDefaultCollation(string $defaultCollation): void
	{
		$this->defaultCollation = $defaultCollation;
	}
	
	public function setDefaultConstraintActions(string $onUpdate, string $onDelete): void
	{
		$this->defaultConstraintActionOnUpdate = $onUpdate;
		$this->defaultConstraintActionOnDelete = $onDelete;
	}
	
	public function getDefaultConstraintActionOnUpdate(): string
	{
		return $this->defaultConstraintActionOnUpdate;
	}
	
	public function getDefaultConstraintActionOnDelete(): string
	{
		return $this->defaultConstraintActionOnDelete;
	}
	
	public function getDefaultPrimaryKey(string $class): Column
	{
		$config = $this->defaultPrimaryKeyConfiguration;
		
		$column = new Column($class, null);
		
		if (isset($config['propertyType'])) {
			$column->setPropertyType($config['propertyType']);
			unset($config['propertyType']);
		}
		
		$column->setPrimaryKey(true);
		$column->loadFromArray($config);
		
		return $column;
	}
	
	public function setDefaultPrimaryKeyConfiguration(array $configuration): void
	{
		$this->defaultPrimaryKeyConfiguration = $configuration;
	}
	
	public function dumpStructure(): string
	{
		$tables = [];
		$sql = '';
		
		foreach ($this->connection->findAllRepositories() as $repositoryName) {
			$class = $this->getEntityClass($repositoryName);
			
			$structure = $this->schemaManager->getStructure($class, new Cache(new DevNullStorage()), $this->getDefaultPrimaryKey($class));
			
			$entityTable = $structure->getTable();
			$entityColumns = $this->parseColumns($structure->getColumns(), $this->connection->getAvailableMutations());
			$tables[$entityTable->getName()] = $entityTable->getName();
			
			$sql .= $this->getSqlCreateTable($entityTable, $entityColumns);
		}
		
		foreach ($this->connection->findAllRepositories() as $repositoryName) {
			$class = $this->getEntityClass($repositoryName);
			$structure = $this->schemaManager->getStructure($class, new Cache(new DevNullStorage()), $this->getDefaultPrimaryKey($class));
			$entityIndexes = $this->parseIndexes($structure->getIndexes(), $this->connection->getAvailableMutations(), $structure);
			
			$sql .= $this->getSqlAddMetas($structure->getTable(), $structure->getConstraints(), $entityIndexes, $structure->getTriggers());
		}
		
		foreach ($this->connection->findAllRepositories() as $repositoryName) {
			$class = $this->getEntityClass($repositoryName);
			$structure = $this->schemaManager->getStructure($class, new Cache(new DevNullStorage()), $this->getDefaultPrimaryKey($class));
			
			foreach ($structure->getRelations() as $relation) {
				if ($relation instanceof \StORM\Meta\RelationNxN && !isset($tables[$relation->getVia()])) {
					[$entityTable, $entityColumns, $entityConstraints, $entityIndexes] = $this->parseNxNRelation($relation);
					$sql .= $this->getSqlCreateTable($entityTable, $entityColumns);
					$sql .= $this->getSqlAddMetas($entityTable, $entityConstraints, $entityIndexes, []);
				}
			}
		}
		
		return $sql;
	}
	
	public function dumpRealStructure(): string
	{
		$sql = '';
		
		foreach ($this->getTables() as $table) {
			$sql .= $this->getSqlCreateTable($table, $this->getColumns($table->getName()));
		}
		
		foreach ($this->getTables() as $table) {
			$tableName = $table->getName();
			$sql .= $this->getSqlAddMetas($table, $this->getConstraints($tableName), $this->getIndexes($tableName), $this->getTriggers($tableName));
		}
		
		return $sql;
	}
	
	public function dumpAlters(): string
	{
		$sql = '';
		$tableExists = [];
		$columnsByTableName = [];
		
		foreach ($this->connection->findAllRepositories() as $repositoryName) {
			$class = $this->getEntityClass($repositoryName);
			
			$structure = $this->schemaManager->getStructure($class, new Cache(new DevNullStorage()), $this->getDefaultPrimaryKey($class));
			
			$entityColumns = $this->parseColumns($structure->getColumns(), $this->connection->getAvailableMutations());
			$columnsByTableName[$structure->getTable()->getName()] = $entityColumns;
			
			$tableExists[$repositoryName] = $this->getTable($structure->getTable()->getName()) !== null;
			
			if ($tableExists[$repositoryName]) {
				$sql .= $this->getSqlSyncTable($this->getTable($structure->getTable()->getName()), $structure->getTable(), $this->getColumns($structure->getTable()->getName()), $entityColumns);
			} else {
				$sql .= $this->getSqlCreateTable($structure->getTable(), $entityColumns);
			}
		}
		
		foreach ($this->connection->findAllRepositories() as $repositoryName) {
			$class = $this->getEntityClass($repositoryName);
			$structure = $this->schemaManager->getStructure($class, new Cache(new DevNullStorage()), $this->getDefaultPrimaryKey($class));
			$tableName = $structure->getTable()->getName();
			$entityIndexes = $this->parseIndexes($structure->getIndexes(), $this->connection->getAvailableMutations(), $structure);
			
			if ($tableExists[$repositoryName]) {
				$sql .= $this->getSqlSyncMetas(
					$tableName,
					$this->getConstraints($tableName),
					$structure->getConstraints(),
					$this->getIndexes($tableName),
					$entityIndexes,
					$this->getTriggers($tableName),
					$structure->getTriggers(),
				);
			} else {
				$sql .= $this->getSqlAddMetas($structure->getTable(), $structure->getConstraints(), $entityIndexes, $structure->getTriggers());
			}
			
			foreach ($structure->getRelations() as $relation) {
				if ($relation instanceof \StORM\Meta\RelationNxN) {
					$nxnTableName = $relation->getVia();
					[$entityTable, $entityColumns, $entityConstraints, $entityIndexes] = $this->parseNxNRelation($relation);
					
					$nxnTable = $this->getTable($nxnTableName);
					
					if (!$nxnTable) {
						$sql .= $this->getSqlCreateTable($entityTable, $entityColumns);
						$sql .= $this->getSqlAddMetas($entityTable, $entityConstraints, $entityIndexes, []);
					} else {
						// unset if key is defined column
						if (isset($columnsByTableName[$relation->getVia()])) {
							foreach (\array_keys($entityColumns) as $name) {
								if (isset($columnsByTableName[$relation->getVia()][$name])) {
									unset($entityColumns[$name]);
								}
							}
						}
						
						$sql .= $this->getSqlSyncTable($this->getTable($nxnTableName), $entityTable, $this->getColumns($nxnTableName), $entityColumns);
						$sql .= $this->getSqlSyncMetas($nxnTableName, $this->getConstraints($nxnTableName), $entityConstraints, $this->getIndexes($nxnTableName), $entityIndexes, [], []);
					}
				}
			}
		}
		
		return $sql;
	}
	
	public function dumpCleanAlters(): string
	{
		// @TODO  if not found drop columns
		// @TODO  if not found drop columns
		
		return '';
	}
	
	public function getSqlVersion(): string
	{
		return $this->getConnection()->getLink()->getAttribute(\PDO::ATTR_SERVER_VERSION);
	}

	protected function compare(ISqlEntity $entity, ISqlEntity $toCompareEntity): bool
	{
		$match = $entity->getSqlProperties() === $toCompareEntity->getSqlProperties();
		
		if (!$match) {
			Arrays::invoke($this->onCompareFail, \get_class($entity), $entity->getSqlProperties(), $toCompareEntity->getSqlProperties());
		}
		
		return $match;
	}
	
	/**
	 * @param \StORM\Meta\Table $table
	 * @param array<\StORM\Meta\Constraint> $constraints
	 * @param array<\StORM\Meta\Index> $indexes
	 * @param array<\StORM\Meta\Trigger> $triggers
	 */
	protected function getSqlAddMetas(Table $table, array $constraints = [], array $indexes = [], array $triggers = []): string
	{
		$sql = '';
		
		$sqlEntities = [
			\Migrator\SqlGenerator\Constraint::class => $constraints,
			\Migrator\SqlGenerator\Index::class => $indexes,
			\Migrator\SqlGenerator\Trigger::class => $triggers,
		];
		
		foreach ($sqlEntities as $class => $entityToCreate) {
			foreach ($entityToCreate as $entity) {
				$entitySql = new $class($this, $table->getName(), $entity);
				$sql .= $entitySql->getAdd();
			}
		}
		
		return $sql;
	}
	
	/**
	 * @param array<\StORM\Meta\Column> $columns
	 * @param array<string> $mutations
	 * @return array<\StORM\Meta\Column> $columns
	 */
	protected function parseColumns(array $columns, array $mutations): array
	{
		$parsed = [];
		
		foreach ($columns as $column) {
			if ($column->hasMutations()) {
				foreach ($mutations as $mutationSuffix) {
					$newName = $column->getName() . $mutationSuffix;
					$parsed[$newName] = clone $column;
					$parsed[$newName]->setName($newName);
				}
			} else {
				$parsed[$column->getName()] = $column;
			}
		}
		
		return $parsed;
	}
	
	/**
	 * @param array<\StORM\Meta\Index> $indexes
	 * @param array<string> $mutations
	 * @param \StORM\Meta\Structure $structure
	 * @return array<\StORM\Meta\Index> $indexes
	 */
	protected function parseIndexes(array $indexes, array $mutations, Structure $structure): array
	{
		$parsed = [];
		
		foreach ($indexes as $index) {
			if ($index->hasMutations()) {
				foreach ($mutations as $mutationSuffix) {
					$newName = $index->getName() . $mutationSuffix;
					$parsed[$newName] = clone $index;
					$parsed[$newName]->setName($newName);
					
					$columns = [];
					
					foreach ($index->getColumns() as $columnName) {
						$columns[] = $structure->getColumn($columnName)->hasMutations() ? $columnName . $mutationSuffix : $columnName;
					}
					
					$parsed[$newName]->setColumns($columns);
				}
			} else {
				$parsed[$index->getName()] = $index;
			}
		}
		
		return $parsed;
	}
	
	/**
	 * @param \StORM\Meta\RelationNxN $relation
	 * @return array<mixed>
	 */
	protected function parseNxNRelation(RelationNxN $relation): array
	{
		$tableName = $relation->getVia();
		
		$table = new \StORM\Meta\Table($tableName);
		$leftColumn = new \StORM\Meta\Column($tableName, $relation->getSourceViaKey());
		$leftColumn->setName($relation->getSourceViaKey());
		$leftColumn->setPropertyType($relation->getSourceKeyType());
		$leftColumn->setPrimaryKey(true);
		
		$rightColumn = new \StORM\Meta\Column($tableName, $relation->getTargetViaKey());
		$rightColumn->setName($relation->getTargetViaKey());
		$rightColumn->setPropertyType($relation->getTargetKeyType());
		$rightColumn->setPrimaryKey(true);
		$columns = [
			$leftColumn->getName() => $leftColumn,
			$rightColumn->getName() => $rightColumn,
		];
		
		$leftConstraint = new \StORM\Meta\Constraint($relation->getVia(), '');
		$leftConstraint->setDefaultsFromRelationNxN($this->schemaManager, $relation, 'source');
		$leftConstraint->setOnUpdate('CASCADE');
		$leftConstraint->setOnDelete('CASCADE');
		
		$rightConstraint = new \StORM\Meta\Constraint($relation->getVia(), '');
		$rightConstraint->setDefaultsFromRelationNxN($this->schemaManager, $relation, 'target');
		$rightConstraint->setOnUpdate('CASCADE');
		$rightConstraint->setOnDelete('CASCADE');
		
		$constraints = [
			$leftConstraint->getName() => $leftConstraint,
			$rightConstraint->getName() => $rightConstraint,
		];
		$leftIndex = new \StORM\Meta\Index($relation->getVia());
		$leftIndex->setName($relation->getVia() . \StORM\Meta\Structure::NAME_SEPARATOR . $leftColumn->getName());
		$leftIndex->addColumn($leftColumn->getName());
		
		$rightIndex = new \StORM\Meta\Index($relation->getVia());
		$rightIndex->setName($relation->getVia() . \StORM\Meta\Structure::NAME_SEPARATOR . $rightColumn->getName());
		$rightIndex->addColumn($rightColumn->getName());
		
		$indexes = [
			$leftIndex->getName() => $leftIndex,
			$rightIndex->getName() => $rightIndex,
		];
		
		return [$table, $columns, $constraints, $indexes];
	}
	
	/**
	 * @param \StORM\Meta\Table $table
	 * @param array<\StORM\Meta\Column> $columns
	 */
	protected function getSqlCreateTable(Table $table, array $columns): string
	{
		$tableSql = new \Migrator\SqlGenerator\Table($this, $table);
		
		return $tableSql->getAdd($columns);
	}
	
	/**
	 * @param \StORM\Meta\Table $fromTable
	 * @param \StORM\Meta\Table $toTable
	 * @param array<\StORM\Meta\Column> $fromColumns
	 * @param array<\StORM\Meta\Column> $toColumns
	 */
	protected function getSqlSyncTable(Table $fromTable, Table $toTable, array $fromColumns, array $toColumns): string
	{
		$sql = '';
		$tableSql = new \Migrator\SqlGenerator\Table($this, $fromTable);
		$entitySql = new \Migrator\SqlGenerator\Table($this, $toTable);
		
		if (!$this->compare($tableSql, $entitySql)) {
			$sql .= $entitySql->getChange($fromTable->getName());
		}
		
		$sqlEntities = [
			\Migrator\SqlGenerator\Column::class => [$fromColumns, $toColumns],
		];
		
		foreach ($sqlEntities as $class => $entitiesToCompare) {
			[$fromEntities, $toEntities] = $entitiesToCompare;
			
			foreach ($toEntities as $name => $entity) {
				// add
				if (!isset($fromEntities[$name])) {
					$sqlEntity = new $class($this, $fromTable->getName(), $entity);
					$sql .= $sqlEntity->getAdd();
					
					continue;
				}
				
				// modify
				$sqlEntity = new $class($this, $fromTable->getName(), $entity);
				
				if ($this->compare($sqlEntity, new $class($this, $fromTable->getName(), $fromEntities[$name]))) {
					continue;
				}
				
				$sql .= $sqlEntity->getChange($fromEntities[$name]->getName());
			}
		}
		
		return $sql;
	}
	
	/**
	 * @param string $fromTableName
	 * @param array<\StORM\Meta\Constraint> $fromConstraints
	 * @param array<\StORM\Meta\Constraint> $toConstraints
	 * @param array<\StORM\Meta\Index> $fromIndexes
	 * @param array<\StORM\Meta\Index> $toIndexes
	 * @param array<\StORM\Meta\Trigger> $fromTriggers
	 * @param array<\StORM\Meta\Trigger> $toTriggers
	 */
	protected function getSqlSyncMetas(
		string $fromTableName,
		array $fromConstraints,
		array $toConstraints,
		array $fromIndexes,
		array $toIndexes,
		array $fromTriggers,
		array $toTriggers
	): string {
		$sql = '';
		
		$sqlEntities = [
			\Migrator\SqlGenerator\Constraint::class => [$fromConstraints, $toConstraints],
			\Migrator\SqlGenerator\Index::class => [$fromIndexes, $toIndexes],
			\Migrator\SqlGenerator\Trigger::class => [$fromTriggers, $toTriggers],
		];
		
		foreach ($sqlEntities as $class => $entitiesToCompare) {
			[$fromEntities, $toEntities] = $entitiesToCompare;
			
			foreach ($toEntities as $name => $entity) {
				// add
				if (!isset($fromEntities[$name])) {
					$sqlEntity = new $class($this, $fromTableName, $entity);
					$sql .= $sqlEntity->getAdd();
					
					continue;
				}
				
				// modify
				$sqlEntity = new $class($this, $fromTableName, $entity);
				
				if ($this->compare($sqlEntity, new $class($this, $fromTableName, $fromEntities[$name]))) {
					continue;
				}
				
				$sql .= $sqlEntity->getChange($fromEntities[$name]->getName());
			}
		}
		
		return $sql;
	}
	
	/**
	 * @param \StORM\Meta\Table $fromTable
	 * @param array<\StORM\Meta\Column> $fromColumns
	 * @param array<\StORM\Meta\Column> $toColumns
	 * @param array<\StORM\Meta\Constraint> $fromConstraints
	 * @param array<\StORM\Meta\Constraint> $toConstraints
	 * @param array<\StORM\Meta\Index> $fromIndexes
	 * @param array<\StORM\Meta\Index> $toIndexes
	 * @param array<\StORM\Meta\Trigger> $fromTriggers
	 * @param array<\StORM\Meta\Trigger> $toTriggers
	 */
	protected function getCleanTableSql(
		Table $fromTable,
		array $fromColumns,
		array $toColumns,
		array $fromConstraints,
		array $toConstraints,
		array $fromIndexes,
		array $toIndexes,
		array $fromTriggers,
		array $toTriggers
	): string {
		$sql = '';
		
		$sqlEntities = [
			\Migrator\SqlGenerator\Column::class => [$fromColumns, $toColumns],
			\Migrator\SqlGenerator\Constraint::class => [$fromConstraints, $toConstraints],
			\Migrator\SqlGenerator\Index::class => [$fromIndexes, $toIndexes],
			\Migrator\SqlGenerator\Trigger::class => [$fromTriggers, $toTriggers],
		];
		
		foreach ($sqlEntities as $class => $entitiesToCompare) {
			[$fromEntities, $toEntities] = $entitiesToCompare;
			
			foreach ($fromEntities as $name => $entity) {
				if (!isset($toEntities[$name])) {
					$sqlEntity = new $class($this, $fromTable->getName(), $entity);
					$sql .= $sqlEntity->getDrop();
				}
			}
		}
		
		return $sql;
	}
	
	protected function getSqlDropTable(Table $table): string
	{
		$sqlTable = new \Migrator\SqlGenerator\Table($this, $table);
		
		return $sqlTable->getDrop() . \PHP_EOL;
	}
	
	/**
	 * @param \StORM\Meta\Table $table
	 * @param array<\StORM\Meta\Constraint> $constraints
	 * @param array<\StORM\Meta\Index> $indexes
	 * @param array<\StORM\Meta\Trigger> $triggers
	 */
	protected function getSqlDropMetas(Table $table, array $constraints, array $indexes, array $triggers): string
	{
		$sql = '';
		
		$sqlEntities = [
			\Migrator\SqlGenerator\Constraint::class => $constraints,
			\Migrator\SqlGenerator\Index::class => $indexes,
			\Migrator\SqlGenerator\Trigger::class => $triggers,
		];
		
		foreach ($sqlEntities as $class => $sqlEntity) {
			foreach ($sqlEntity as $entity) {
				$sqlEntity = new $class($this, $table->getName(), $entity);
				$sql .= $sqlEntity->getDrop();
			}
		}
		
		return $sql;
	}
	
	private function getSqlDefaultAction(): string
	{
		return $this->sqlDefaultAction ?? $this->sqlDefaultAction = \version_compare($this->getSqlVersion(), '8.0.0', '>=') ? 'NO ACTION' : 'RESTRICT';
	}
	
	private function getEntityClass(string $repositoryName): string
	{
		return Structure::getEntityClassFromRepositoryClass(\get_class($this->connection->findRepositoryByName($repositoryName)));
	}
}
