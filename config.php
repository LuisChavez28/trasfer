<?php 
require __DIR__.'/vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

function getDB(){
	$serviceAccount = ServiceAccount::fromJsonFile(__DIR__.'/secret/.json');
	$firebase = (new Factory)
	    ->withServiceAccount($serviceAccount)
	    ->create();
	return $firebase->getDatabase();
}
?>