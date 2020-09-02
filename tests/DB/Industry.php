<?php

namespace DB;

/**
 * @table{"name":"stocks_industry"}
 */
class Industry extends \StORM\Entity // @codingStandardsIgnoreLine
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
	 * @relation
	 * @constraint{"onDelete":"CASCADE"}
	 * @var \DB\Type
	 */
	public $type;
}
