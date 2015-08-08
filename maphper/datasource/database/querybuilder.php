<?php
namespace Maphper\DataSource\Database;
class QueryBuilder {
	private function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}	

	public function delete($table, array $criteria, $args, $limit = null, $offset = null) {
		$limit = $limit ? ' LIMIT ' . $limit : '';
		$offset = $offset ? ' OFFSET ' . $offset : '';
		return new Query('DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $criteria) . $limit . $offset, $args);
	}

	public function select($table, array $criteria, $args, $options = []) {
		$where = count($criteria) > 0 ? ' WHERE ' . implode(' AND ', $criteria) : '';
		//$limit = $limit ? ' LIMIT ' . $limit : ''; 
		$limit = (isset($options['limit'])) ? ' LIMIT ' . $options['limit'] : '';
		
		if (isset($options['offset'])) {
			$offset = ' OFFSET ' . $options['offset'];
			if (!$limit) $limit = ' LIMIT  1000';
		}
		else $offset = '';

		$order = isset($options['order']) ? ' ORDER BY ' . $options['order'] : '';
		return new Query('SELECT * FROM ' . $table . ' ' . $where . $order . $limit . $offset, $args);
	}
	
	public function aggregate($table, $function, $field, $where, $args, $group) {
		if ($group == true) $groupBy = ' GROUP BY ' . $field;
		else $groupBy = '';
		return new Query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '   FROM ' . $table . ($where[0] != null ? ' WHERE ' : '') . implode(' AND ', $where) . ' ' . $groupBy, $args);
	}

	private function buildSaveQuery($data, $prependField = false) {
		$sql = [];
		$args = [];
		foreach ($data as $field => $value) {
			//For dates with times set, search on time, if the time is not set, search on date only.
			//E.g. searching for all records posted on '2015-11-14' should return all records that day, not just the ones posted at 00:00:00 on that day
			if ($value instanceof \DateTime) {
				if ($value->format('H:i:s')  == '00:00:00') $value = $value->format('Y-m-d');
				else $value = $value->format('Y-m-d H:i:s');
			}
			if (is_object($value)) continue;
			if ($prependField){
				$sql[] = $this->quote($field) . ' = :' . $field;
			} else {
				$sql[] = ':' . $field;
			}
			$args[$field] = $value;			
		}
		return ['sql' => $sql, 'args' => $args];
	}
	
	public function insert($table, $data) {
		$query = $this->buildSaveQuery($data);
		return new Query('INSERT INTO ' . $this->quote($table) . ' (' .implode(', ', array_keys($query['args'])).') VALUES ( ' . implode(', ', $query['sql']). ' )', $query['args']);
	}

	public function update($table, array $primaryKey, $data) {
		$query = $this->buildSaveQuery($data, true);
		$where = [];
		foreach($primaryKey as $field) $where[] = $this->quote($field) . ' = :' . $field;
		return new Query('UPDATE ' . $this->quote($table) . ' SET ' . implode(', ', $query['sql']). ' WHERE '. implode(' AND ', $where), $query['args']);
	}

	public function selectBuilder($fields, $mode){
		$selectBuilder = new SelectBuilder;
		return $selectBuilder->createSql($fields, $mode);
	}
}