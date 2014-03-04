<?php

class Pongo
{
	private $pdo;
	private $connected = false;
	private $language_id = null;

	public function __construct($params)
	{
		$defaults = array(
			'host' => 'localhost',
			'port' => 3306,
			'username' => 'root',
			'password' => '',
			'prefix' => 'pongo_'
		);


		$params = array_merge($defaults, $params);
		$this->prefix = $params['prefix'];
		$this->dbname = str_replace('`', '', $params['dbname']);

		$conn_str = "mysql:host={$params['host']};port={$params['port']};dbname={$this->dbname}";
		
		if (!empty($params['dbname']))
		{
			try {
				$this->pdo = new PDO($conn_str, $params['username'], $params['password']);
				$this->connected = true;
			}
			catch (PDOException $e) {
				$this->connected = false;
			}
		}

		if ($this->connected)
		{
			$this->setupDatabase();
		}
	}

	public function isConnected()
	{
		return $this->connected;
	}

	public function setupDatabase()
	{
		$name = $this->prefix.'entity_type';
		$stm = $this->pdo->prepare('show tables like :name');
		$stm->bindParam(':name', $name);
		if($stm->execute())
		{
			if ($stm->fetch())
			{
				// Tables exist, yay!
			}
			else
			{
				$path_to_sql = dirname(__FILE__).'/../../config/db_structure.sql';
				$sql = str_replace('`mydb`', '`'.$this->dbname.'`', file_get_contents($path_to_sql));
				$sql = str_replace('__prefix__', $this->prefix, $sql);
				try {
					$this->pdo->exec($sql);
				}
				catch (PDOException $e)
				{
					$this->connected = false;
				}
			}
		}
	}

	public function prepare($stm_str)
	{
		return $this->pdo->prepare(str_replace('__prefix__', $this->prefix, $stm_str));
	}

	public function create($table, $data)
	{
		$clauses = array();
		foreach ($data as $key => $value)
		{
			$clauses[":$key"] = $value;
		}
		$create_stm_str = 'INSERT INTO __prefix__'.$table.' ('.implode(', ', array_keys($data)).') VALUES ('.implode(', ', array_keys($clauses)).')';
		$create_stm = $this->prepare($create_stm_str);
		$create_stm->execute($clauses);
		return $this->pdo->lastInsertId();
	}

	public function findOrCreate($table, $data)
	{
		$conditions = array();
		$clauses = array();
		foreach ($data as $key => $value)
		{
			$conditions[] = "$key = :$key";
			$clauses[":$key"] = $value;
		}
		$find_stm_str = 'SELECT id FROM __prefix__'.$table.' WHERE '.implode(' AND ', $conditions);
		$find_stm = $this->prepare($find_stm_str);
		$find_stm->execute($clauses);
		if ($result = $find_stm->fetch())
		{
			return $result['id'];
		}
		else
		{
			$create_stm_str = 'INSERT INTO __prefix__'.$table.' ('.implode(', ', array_keys($data)).') VALUES ('.implode(', ', array_keys($clauses)).')';
			$create_stm = $this->prepare($create_stm_str);
			$create_stm->execute($clauses);
			return $this->pdo->lastInsertId();
		}
	}

	public function findOrCreateEntityTypeId($entity_type)
	{
		return $this->findOrCreate('entity_type', array('name' => $entity_type));
	}

	public function findOrCreateEntityId($entity_type_id, $foreign_identifier)
	{
		return $this->findOrCreate('entity', array(
				'entity_type_id' => $entity_type_id,
				'foreign_identifier' => $foreign_identifier
			)
		);
	}

	public function findOrCreateEntityDimensionId($entity_type_id, $language_id, $name)
	{
		return $this->findOrCreate('entity_dimension_i18n', array(
				'entity_type_id' => $entity_type_id,
				'language_id' => $language_id,
				'name' => $name
			)
		);
	}

	public function insert($entity_type, $entity_name, $characteristics, $language_id=null, $foreign_identifier=null)
	{
		// Sorry for ugly one-liner but pretty simple logic :)
		$language_id = $language_id === null ? ($this->language_id ? $this->language_id : 1) : 1;

		// Get entity type
		$entity_type_id = $this->findOrCreateEntityTypeId($entity_type);

		// Get an identifier for this entity
		if ($foreign_identifier === null)
		{
			$foreign_identifier = is_string($entity_name) ? $entity_name : $entity_name['name'];
		}

		// Get entity id
		$entity_id = $this->findOrCreateEntityId($entity_type_id, $foreign_identifier);

		// Store entity name
		$weight = is_string($entity_name) ? 0 : $entity_name['weight'];
		$this->findOrCreate('entity_i18n', array(
				'entity_id' => $entity_id,
				'language_id' => $language_id,
				'name' => is_string($entity_name) ? $entity_name : $entity_name['name'],
				'weight' => $weight
			)
		);

		// Store characteristics
		$characteristics_for_insertion = array();

		foreach ($characteristics as $name => $value)
		{
			$characteristics_for_insertion[] = array(
				'entity_dimension_id' => $this->findOrCreateEntityDimensionId($entity_type_id, $language_id, $name),
				'language_id' => $language_id,
				'value' => is_string($value) ? $value : $value['value'],
				'weight' => is_string($value) ? 0 : $value['weight']
			);
		}

		foreach ($characteristics_for_insertion as $values)
		{
			$this->create('entity_characteristic_i18n', $values);
		}
	}
}