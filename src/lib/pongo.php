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

	public function find($table, $data)
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
			return null;
		}
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
		static $cache = array();

		if (!isset($cache[$entity_type]))
		{
			$cache[$entity_type] = $this->findOrCreate('entity_type', array('name' => $entity_type));
		}

		return $cache[$entity_type];
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
		static $cache = array();

		$key = "$entity_type_id:$language_id:$name";

		if (!isset($cache[$key]))
		{
			$cache[$key] = $this->findOrCreate('entity_dimension_i18n', array(
					'entity_type_id' => $entity_type_id,
					'language_id' => $language_id,
					'name' => $name
				)
			);
		}

		return $cache[$key];
	}

	public function findEntityId($entity_type, $foreign_identifier, $options=array())
	{
		$stm = $this->prepare('
			SELECT e.id from __prefix__entity_type t
			INNER JOIN __prefix__entity e ON e.entity_type_id = t.id
			WHERE 
			t.name = :entity_type
			AND e.foreign_identifier = :foreign_identifier
		');
		$stm->bindParam(':entity_type', $entity_type);
		$stm->bindParam(':foreign_identifier', $foreign_identifier);
		if (!$stm->execute())
		{
			print_r($this->pdo->errorInfo());
			return null;
		}
		elseif ($result = $stm->fetch())
		{
			return $result['id'];
		}
		else
		{
			return false;
		}
	}

	public function delete($entity_type, $foreign_identifier)
	{
		$entity_id = $this->findEntityId($entity_type, $foreign_identifier);
		if ($entity_id)
		{
			$stm0 = $this->prepare('DELETE FROM __prefix__entity_i18n WHERE entity_id = :entity_id');
			$stm0->bindParam(':entity_id', $entity_id);
			$stm1 = $this->prepare('DELETE FROM __prefix__entity_characteristic_i18n WHERE entity_id = :entity_id');
			$stm1->bindParam(':entity_id', $entity_id);
			return $stm0->execute() && $stm1->execute();
		}
		return true;
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
			$vw = array();

			/*
				$value may be:
				'toto'
				['value' => 'bob', weight' => 1]
				['hello', 'world']
				[['value' => 'bob', weight' => 1], ['value' => 'paf', weight' => 0.5]]
			*/

			if (is_string($value))
			{
				$vw[] = array('value' => $value, 'weight' => 0);
			}
			// Array of values
			else
			{
				if (is_int(key($value)))
				{
					$list = $value;
				}
				else
				{
					$list = array($value);
				}
				foreach ($list as $item)
				{
					if (is_array($item))
					{
						$vw[] = $item;
					}
					else
					{
						$vw[] = array('value' => $item, 'weight' => 0);
					}
				}
			}

			foreach ($vw as $pair)
			{
				$characteristics_for_insertion[] = array(
					'entity_dimension_id' => $this->findOrCreateEntityDimensionId($entity_type_id, $language_id, $name),
					'language_id' => $language_id,
					'value' => $pair['value'],
					'weight' => $pair['weight']
				);
			}
		}

		foreach ($characteristics_for_insertion as $values)
		{
			$this->create('entity_characteristic_i18n', $values);
		}
	}

	public function replace($entity_type, $entity_name, $characteristics, $language_id=null, $foreign_identifier=null)
	{
		// Get an identifier for this entity
		if ($foreign_identifier === null)
		{
			$foreign_identifier = is_string($entity_name) ? $entity_name : $entity_name['name'];
		}
		$this->delete($entity_type, $foreign_identifier);
		$ths->insert($entity_type, $entity_name, $characteristics, $language_id, $foreign_identifier);
	}

	public function getMatchClauseAndScore($field, $query)
	{
		$length = mb_strlen($query);
		if ($length < 5)
		{
			return array(
				'type' => 'like',
				'clause' => "$field LIKE :like",
				'score' => "$length / length($field)",
				'bind' => array(':like' => "$query%")
			);
		}
		else
		{
			
			$m = "MATCH ($field) AGAINST (:match IN BOOLEAN MODE)";
			$tokens = array();
			foreach (preg_split('/\s+/', $query) as $token)
			{
				$tokens[] = "*$token*";
			}
			$q = implode(' ', $tokens);



			return array(
				'type' => 'match',
				'clause' => $m,
				'score' => $m,
				'bind' => array(':match' => $q)
			);
		}
	}

	public function select($entity_type, $conditions, $query, $dimension=null, $language_id=null)
	{
		$language_id = $language_id === null ? ($this->language_id ? $this->language_id : 1) : 1;

		$entities = array();
		$dimensions = array();
		$limit = 10;

		$entity_type_id = $this->find('entity_type', array('name' => $entity_type));

		if ($entity_type_id)
		{
			$cs = $this->getMatchClauseAndScore('ei.name', $query);

			$sql = "SELECT ei.name, %score as score 
			FROM __prefix__entity_i18n ei
			WHERE ei.language_id = :language_id 
			AND %clause
			ORDER BY score DESC 
			LIMIT $limit";

			$sql = str_replace(array('%score', '%clause'), array($cs['score'], $cs['clause']), $sql);
			//die($sql);
			$stm = $this->prepare($sql);
			$stm->bindParam(':language_id', $language_id);

			foreach ($cs['bind'] as $key => $value)
			{
				$stm->bindParam($key, $value);
			}

			if ($stm->execute())
			{
				while ($row = $stm->fetch())
				{
					$entities[] = array(
						'score' => $row['score'],
						'data' => array(
							'name' => $row['name'],
							'handle' => $row['name']
						)
					);
				}
			}

			$cs = $this->getMatchClauseAndScore('edi.name', $query);

			$sql = "SELECT edi.name, %score as score
			FROM __prefix__entity_dimension_i18n edi
			WHERE edi.language_id = :language_id
			AND edi.entity_type_id = $entity_type_id
			AND %clause
			ORDER BY score DESC
			LIMIT $limit
			";

			$sql = str_replace(array('%score', '%clause'), array($cs['score'], $cs['clause']), $sql);

			$stm = $this->prepare($sql);

			foreach ($cs['bind'] as $key => $value)
			{
				$stm->bindParam($key, $value);
			}
			
			$stm->bindParam(':language_id', $language_id);
			if ($stm->execute())
			{
				while ($row = $stm->fetch())
				{
					//print_r($row);
					$dimensions[] = array(
						'score' => $row['score'],
						'data' => array(
							'name' => $row['name'],
							'handle' => $row['name']
						)
					);
				}
			}
		}

		return array(
			'entities' => $entities,
			'dimensions' => $dimensions
		);
	}
}