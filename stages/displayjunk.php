<?php

	require_once 'constants.php';
	require_once 'dumpjunk.php';

	$id = $argv[1];
	$language = $argv[2];
	$dumpjunk = new Dumpjunk();
	
	if(!$dumpjunk->setLanguageContraint($language))	{
		echo "\n\tERROR: Language Constraints not defined in JSON_PRECAST file\n\n";
		exit;
	}
	
	$dumpjunk->sanityCheck($id);
	$dumpjunk->extractJunk($id);
?>
