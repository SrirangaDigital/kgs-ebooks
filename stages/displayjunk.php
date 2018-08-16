<?php

	require_once 'constants.php';
	require_once 'dumpjunk.php';

	$id = $argv[1];
	$language = $argv[2];
	
	$dumpjunk = new Dumpjunk();
	$dumpjunk->$language . extractJunk($id);

?>
