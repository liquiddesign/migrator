<?php

require_once __DIR__ . '/../vendor/autoload.php';

$configFile = __DIR__ . '/configs/config.neon';
$sourceFile = __DIR__ . '/_sql/_test_migrator.sql';
$config = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['default'];
$configS1 = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['sandbox1'];
$configS2 = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['sandbox2'];
$configS3 = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['sandbox3'];

// create test DB and fill with test data
$pdo = new \PDO("$config[driver]:host=$config[host]", $config['user'], $config['password']);
$pdo->query("CREATE DATABASE IF NOT EXISTS $config[dbname] CHARACTER SET $config[charset] COLLATE $config[collate]");
$pdo->query("CREATE DATABASE IF NOT EXISTS $configS1[dbname] CHARACTER SET $configS1[charset] COLLATE $configS1[collate]");
$pdo->query("CREATE DATABASE IF NOT EXISTS $configS2[dbname] CHARACTER SET $configS2[charset] COLLATE $configS2[collate]");
$pdo->query("CREATE DATABASE IF NOT EXISTS $configS3[dbname] CHARACTER SET $configS3[charset] COLLATE $configS3[collate]");
$pdo->query("USE $config[dbname]");
$pdo->query(\file_get_contents($sourceFile));
$pdo->query("USE $configS3[dbname]");
$pdo->query(\file_get_contents($sourceFile));
