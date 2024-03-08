<?php

namespace Migrator;

use Composer\Script\Event;
use Nette\DI\Container;
use Nette\Utils\Strings;
use StORM\DIConnection;
use Tracy\Debugger;

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

		if (!Strings::trim($sql)) {
			$event->getIO()->write('Nothing to dump!');

			return;
		}

		if (!$event->getIO()->askConfirmation('Execute SQL command? (y)')) {
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
		$i = 0;

		try {
			$container = static::getDIContainer($arguments);

			$migrator = $container->getByType(Migrator::class);

			$migrator->onCompareFail[] = function ($class, $from, $to) use ($migrator, &$i): void {
				if ($migrator->isDebug()) {
					Debugger::log($class);
					Debugger::log($from);
					Debugger::log($to);

					$i++;
				}
			};
			$sql = $migrator->dumpAlters();
		} catch (\Throwable $exception) {
			$event->getIO()->writeError($exception->getMessage());
			$event->getIO()->writeError($exception->getTraceAsString());

			return;
		}

		$event->getIO()->write($sql);

		if (!Strings::trim($sql)) {
			$event->getIO()->write('Everything is synchronized. Good job!');

			return;
		}

		if ($migrator->isDebug()) {
			$event->getIO()->write('Debug mode is ON! Open tracy log for more details. Match fails: ' . $i);
		}

		if (!$event->getIO()->askConfirmation('Execute SQL command? (y)')) {
			return;
		}

		$connection = $container->getByType(DIConnection::class);

		if ($migrator->isDebug()) {
			$fp = \fopen('php://memory', 'r+');
			\fputs($fp, $sql);
			\rewind($fp);

			while ($line = \fgets($fp)) {
				$event->getIO()->write('Execing ... ' . Strings::trim($line));
				$connection->query($line);
			}
		} else {
			$connection->query($sql);
		}

		$sql = $migrator->dumpAlters();

		if (!Strings::trim($sql)) {
			$event->getIO()->write('Everything is synchronized. Good job!');
		} else {
			$event->getIO()->writeError(' Synchronization failed!');
		}
	}

	public static function cleanDatabase(Event $event): void
	{
		$arguments = $event->getArguments();

		$container = static::getDIContainer($arguments);

		$migrator = $container->getByType(Migrator::class);
		$sql = $migrator->dumpCleanAlters();

		$event->getIO()->write($sql);

		if (!Strings::trim($sql)) {
			$event->getIO()->write('Everything is clean. Good job!');

			return;
		}

		if (!$event->getIO()->askConfirmation('Execute SQL command? (y)')) {
			return;
		}

		$connection = $container->getByType(DIConnection::class);

		if ($migrator->isDebug()) {
			$fp = \fopen('php://memory', 'r+');
			\fputs($fp, $sql);
			\rewind($fp);

			while ($line = \fgets($fp)) {
				$event->getIO()->write('Execing ... ' . Strings::trim($line));
				$connection->query($line);
			}
		} else {
			$connection->query($sql);
		}

		$sql = $migrator->dumpCleanAlters();

		if (!Strings::trim($sql)) {
			$event->getIO()->write('Everything is clean. Good job!');
		} else {
			$event->getIO()->writeError(' Clean failed!');
		}
	}

	protected static function getDIContainer(array $arguments): Container
	{
		if (isset($arguments[0]) && \is_file(\dirname(__DIR__, 4) . '/' . $arguments[0])) {
			return require_once \dirname(__DIR__, 4) . '/' . $arguments[0];
		}

		$class = isset($arguments[0]) && \class_exists($arguments[0]) ? $arguments[0] : '\App\Bootstrap';

		return \method_exists($class, 'createContainer') ? $class::createContainer() : $class::boot()->createContainer();
	}
}
