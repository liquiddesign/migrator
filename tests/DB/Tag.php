<?php

namespace DB;

/**
 * @table{"name":"stocks_tag"}
 */
class Tag extends \StORM\Entity // @codingStandardsIgnoreLine
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
}
