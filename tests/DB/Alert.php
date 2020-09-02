<?php

namespace DB;

/**
 * @testClass
 * @table{"name":"stocks_alert"}
 */
class Alert extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @pk
	 * @var string
	 */
	public $uuid;
	
	/**
	 * @testProperty
	 * @column
	 * @var string
	 */
	public $name;
}
