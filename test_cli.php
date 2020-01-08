<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php');
// require_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Command' . DIRECTORY_SEPARATOR .'CacaCommand.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

$application = new Application('test', '1.0');

// ... register commands

$class = new \CacaCommand();

echo "caca1\n";
// print_r($application);
$application->run($class);

// ...
// $application->add(new GenerateAdminCommand());
$cmd = 'poop';
echo "caca2\n";


$application->add($cmd);
echo "caca3\n";