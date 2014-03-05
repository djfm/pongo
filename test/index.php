<?php

@ini_set('display_errors', 'on');

require_once dirname(__FILE__).'/../src/lib/pongo.php';

$p = new Pongo(array('username' => 'root', 'password' => '', 'dbname' => 'pongo'));

if ($p->isConnected())
{
	echo "Connected!<BR/>";
}
else
{
	die("Could not connect to mysql.");
}

$id = $p->findOrCreateEntityTypeId('Test');
echo "id: $id<BR/>";

$p->insert('Customer', 'John Doe', array('firstname' => 'John', 'lastname' => 'Doe'));
$p->delete('Customer', 'John Doe');