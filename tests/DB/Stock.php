<?php

namespace DB;

use StORM\Entity;

/**
 * Table of stocks
 * @table{"name":"stocks_stock"}
 */
class Stock extends Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @pk
	 * @var string
	 */
	public $uuid;
	
	/**
	 * @column
	 * @var string
	 */
	public $name;
	
	/**
	 * Stock currency CZK, TEST
	 * @column
	 * @var string
	 */
	public $currency;
	
	/**
	 * @column{"name":"is_enabled"}
	 * @var string
	 */
	public $isEnabled;
	
	/**
	 * @relation
	 * @constraint
	 * @var \DB\Sector
	 */
	public $sector;
	
	/**
	 * @relation
	 * @constraint
	 * @var \DB\Industry
	 */
	public $industry;
	
	/**
	 * @relation
	 * @var \DB\Alert[]|\StORM\ICollectionRelation
	 */
	public $alerts;
	
	/**
	 * @relationNxN
	 * @var \DB\Tag[]|\StORM\ICollectionRelation
	 */
	public $tags;
	
	/**
	 * @var string
	 */
	public $nonColumn;
}
