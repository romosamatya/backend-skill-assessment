<?php 
require_once 'vendor/autoload.php';
require_once 'src/Command/SearchCommand.php';

use Symfony\Component\Console\Application;
use Osky\SearchCommand;

$application = new Application();
$application->add(new SearchCommand());
$application->run();
 ?>
