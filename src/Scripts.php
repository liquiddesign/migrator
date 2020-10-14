<?php

namespace Migrator;

use Composer\Script\Event;
use StORM\DIConnection;

class Scripts
{
	/**
	 * Trigger as event from composer
	 * @param \Composer\Script\Event $event Composer event
	 */
	public static function createDatabase(Event $event): void
	{
		$arguments = $event->getArguments();
		
		$class = $arguments[0] ?? '\App\Bootstrap';
		
		$container = \method_exists($class, 'createContainer') ? $class::createContainer() : $class::boot()->createContainer();
		
		$migrator = $container->getByType(Migrator::class);
		$sql = $migrator->dumpStructure();
		$event->getIO()->write($sql);
		
		if (!$event->getIO()->askConfirmation("Execute SQL command? (n)", false)) {
			return;
		}

		$container->getByType(DIConnection::class)->query($sql);
	}
	
	/**
	 * Trigger as event from composer
	 * @param \Composer\Script\Event $event Composer event
	 */
	public static function syncDatabase(Event $event): void
	{
		$arguments = $event->getArguments();
		
		$class = $arguments[0] ?? '\App\Bootstrap';
		
		$container = \method_exists($class, 'createContainer') ? $class::createContainer() : $class::boot()->createContainer();
		
		$migrator = $container->getByType(Migrator::class);
		$sql = $migrator->dumpAlters();
		$event->getIO()->write($sql);
		
		if (!$event->getIO()->askConfirmation("Execute SQL command? (n)", false)) {
			return;
		}

		$container->getByType(DIConnection::class)->query($sql);
	}
}
