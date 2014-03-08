<?php

@ini_set('display_errors', 'on');

$settings = require_once dirname(__FILE__).'/../settings.php';
require_once dirname(__FILE__).'/../src/lib/pongo.php';

header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
$pongo = new Pongo($settings);

$entities = array();
$dimensions = array();
$filters = array();

$results = $pongo->select(
	$input['type'],
	$input['conditions'],
	$input['query'],
	isset($input['dimension']) ? $input['dimension'] : null,
	isset($input['language_id']) ? $input['language_id'] : null
);

$entities = isset($results['entities']) ? $results['entities'] : array();
$dimensions = isset($results['dimensions']) ? $results['dimensions'] : array();
$filters = isset($results['filters']) ? $results['filters'] : array();

die(json_encode(array(
	'entities' => $entities,
	'dimensions' => $dimensions,
	'filters' => $filters
)));