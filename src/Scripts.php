<?php

namespace Migrator;

use Composer\Script\Event;
use Nette\DI\Container;
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
		
		$container = static::getDIContainer($arguments);
		
		$migrator = $container->getByType(Migrator::class);
		$sql = $migrator->dumpStructure();
		$event->getIO()->write($sql);
		
		if (!\trim($sql)) {
			$event->getIO()->write('Nothing to dump!');
			
			return;
		}
		
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
		
		$container = static::getDIContainer($arguments);
		
		$migrator = $container->getByType(Migrator::class);
		$sql = $migrator->dumpAlters();
		$event->getIO()->write($sql);
		
		if (!\trim($sql)) {
			$event->getIO()->write('Everything is synchronized. Good job!');
			
			return;
		}
		
		if (!$event->getIO()->askConfirmation("Execute SQL command? (n)", false)) {
			return;
		}

		$container->getByType(DIConnection::class)->query($sql);
		
		$sql = $migrator->dumpAlters();
		
		if (!\trim($sql)) {
			$event->getIO()->write('Everything is synchronized. Good job!');
		} else {
			$event->getIO()->writeError(' Synchronization failed!');
		}
	}
	
	private static function getDIContainer(array $arguments): Container
	{
		if (isset($arguments[0]) && \is_file(\dirname(__DIR__, 4) . '/' . $arguments[0])) {
			return require_once \dirname(__DIR__, 4) . '/' . $arguments[0];
		}

		$class = isset($arguments[0]) && \class_exists($arguments[0]) ? $arguments[0] : '\App\Bootstrap';
		
		return \method_exists($class, 'createContainer') ? $class::createContainer() : $class::boot()->createContainer();
	}
}
