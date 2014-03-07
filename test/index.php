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

$json = json_decode(file_get_contents(dirname(__FILE__).'/sample.json'), true);

foreach ($json['data'] as $key => $data)
{
	if (isset($data['characteristics']))
	{
		$p->insert($data['type'], $key, $data['characteristics']);
	}
}