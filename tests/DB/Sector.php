<?php

namespace DB;

/**
 * @table{"name":"stocks_sector"}
 * @index{"name":"no_stocks","columns":["no_stocks"]}
 */
class Sector extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @pk
	 * @var string
	 */
	public $uuid;
	
	/**
	 * @column{"mutations":true}
	 * @var string
	 */
	public $name;
	
	/**
	 * @column
	 * @var float
	 */
	public $performance;
	
	/**
	 * @column
	 * @var bool
	 */
	public $general;
	
	/**
	 * @column
	 * @var int
	 */
	public $no_stocks; // @codingStandardsIgnoreLine
}
