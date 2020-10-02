<?php

namespace Migrator;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use StORM\DIConnection;
use StORM\SchemaManager;

class Scripts
{
	/**
	 * Trigger as event from composer
	 *
	 * @param \Composer\Script\Event $event Composer event
	 * @return void
	 */
	public static function createDatabase(Event $event): void
	{
		$container = \App\Bootstrap::boot()->createContainer();
		
		$migrator = new \Migrator\Migrator($container->getByType(DIConnection::class), $container->getByType(SchemaManager::class));
		$sql = $migrator->dumpStructure();
		$event->getIO()->write($sql);
		
		if ($event->getIO()->askConfirmation("Execute SQL command? (n)", false)) {
			$container->getByType(DIConnection::class)->query($sql);
		}
	}
	
	/**
	 * Trigger as event from composer
	 *
	 * @param \Composer\Script\Event $event Composer event
	 * @return void
	 */
	public static function syncDatabase(Event $event): void
	{
		$container = \App\Bootstrap::boot()->createContainer();
		
		$migrator = new \Migrator\Migrator($container->getByType(DIConnection::class), $container->getByType(SchemaManager::class));
		$sql = $migrator->dumpAlters();
		$event->getIO()->write($sql);
		
		if ($event->getIO()->askConfirmation("Execute SQL command? (n)", false)) {
			$container->getByType(DIConnection::class)->query($sql);
		}
	}
}
