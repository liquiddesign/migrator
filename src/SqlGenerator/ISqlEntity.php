<?php

namespace Migrator\SqlGenerator;

interface ISqlEntity
{
	/**
	 * @return string[]
	 */
	public function getSqlProperties(): array;
	
	public function getDrop(): string;

	public function getChange(string $sourceName): string;
}
