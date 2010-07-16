<?
class Generate_Cms_Controller extends Controller {
	public function __construct() {
		parent::__construct();
	}
	
	public function index() { $this->chose(); }
	
	public function chose() {
		$databaseEntries = Kohana::config('database');
		
		foreach($databaseEntries as $dbName => $dbConfig) {
			echo "<h3>{$dbName}</h3>";
			echo "<p><ul>";
			$db = new Database($dbName);
			
			foreach($db->list_tables() as $tableName) {
				echo "<li>{$tableName}: <a href='".url::current()."/../controller/{$dbName}/{$tableName}'>Controller</a> | <a href='".url::current()."/../model/{$dbName}/{$tableName}'>Model</a></li>";
			}
			
			echo "</p></ul>";
		}
	}
	
	public function model($dbName, $table) {
		$this->db = new Database($dbName);
		$tableList = $this->db->list_tables();
		
		header("Content-Type: text/plain");
		
		$ormName = inflector::singular($table);
		$controllerName = str_replace(' ', '_', ucwords(str_replace('_', ' ', $ormName)));
		
		$outputContent = "<?php\n";
		$outputContent .= "class {$controllerName}_Model extends ORM {\n";
		
		list($processedFields, $relationshipFields) = $this->generateColumnList($table);
		
		$hasOne = array();
		$hasMany = array();
		$hasAndBelongsTo = array();
		
		// generateColumnList will find one-to-one and one-to-many but not one-to-many relationships
		
		foreach($relationshipFields as $relationshipName => $relationshipInfo) {
			if($relationshipInfo['type'] == 'one') {
				$hasOne[] = "'".$relationshipName."'";
			} else if($relationshipInfo['type'] == 'one') {
				$hasAndBelongsTo[] = "'".$relationshipName."'";
			}
		}
		
		// search for one-to-many
		
		foreach($tableList as $tableName) {
			if(array_search($ormName.'_id', array_keys($this->db->list_fields($tableName))) !== FALSE) {
				$hasMany[] = "'".inflector::singular($tableName)."'";
			}
		}
		
		$outputContent .= "\tprotected \$has_many = array(".implode(',', $hasMany).");\n";
		$outputContent .= "\tprotected \$has_one = array(".implode(',', $hasOne).");\n";
		$outputContent .= "\tprotected \$has_and_belongs_to_many = array(".implode(',', $hasAndBelongsTo).");\n";
		$outputContent .= "}\n?>\n";
		
		download::force($ormName.'.php', $outputContent);
	}
	
	public function controller($dbName, $table) {
		$this->db = new Database($dbName);
		$tableList = $this->db->list_tables();
		
		header("Content-Type: text/plain");
		
		echo "<?php\n";
		
		// generate the class name
		$controllerName = str_replace(' ', '_', ucwords(str_replace('_', ' ', $table)));
		echo "class {$controllerName}_Controller extends CMS_Core {\n\n";
		
		// generate the basic fields
		list($processedFieldList, $relationshipFieldList) = $this->generateColumnList($table);
		
		echo "\tprotected \$columns = ";
		echo $this->formatPHP(var_export($processedFieldList, true));
		echo ";\n";
		
		// generation the relationships
		echo "\t\n\tprotected \$relationships = ";
		echo $this->formatPHP(var_export($relationshipFieldList, true));
		echo ";\n";
		
		// generate basic function wireframe
		$ormName = inflector::singular($table);
		echo <<<EOL
	
	function __construct() {
		parent::__construct(__FILE__, '{$ormName}');
		
		\$this->autoRedirect = TRUE;
	}
	
	public function view() {
		parent::view();
	}

EOL;
		
		echo "}\n?>\n";
	}
	
	protected function generateColumnList($tableName, $relationshipTable = false) {
		$fieldList = $this->db->list_fields($tableName);
		
		$processedFieldList = array();
		$relationshipFieldList = array();
		
		foreach($fieldList as $fieldName => $fieldInfo) {
			if($fieldName == 'id') {
				if($relationshipTable) continue;
				
				$processedFieldList[$fieldName] = array(
					'restrict' => 'view'
				);
				
				continue;
			}

			if(strstr($fieldName, '_id') !== FALSE) {
				// then possibly a one to one relationship field
				$relationshipTableName = inflector::plural(substr($fieldName, 0, -3));
				list($processedRelationshipFields) = $this->generateColumnList($relationshipTableName, true);
				
				$relationshipFieldList[$relationshipTableName] = array(
					'type' => 'one',
					'columns' => $processedRelationshipFields
				);

				continue;
			}
			
			$processedFieldList[$fieldName] = array();
			
			if($fieldInfo['type'] == 'int' && strstr($fieldName, 'date') !== FALSE) {
				// then we have an integer based date storage field
				// note that this is a conviention that I use for most of my sites (integer field + date in field name indicates date storage field)
				$processedFieldList[$fieldName]['content'] = 'date';
				$processedFieldList[$fieldName]['class'] = 'datetime';
			} else if($fieldInfo['type'] == 'string' && strstr($fieldName, 'description') !== FALSE) {
				// this is convention, description fields normally require a textarea element in the admin
				$processedFieldList[$fieldName]['type'] = 'textarea';
			}
			
			// print_r($fieldInfo);
		}
		
		// look for pivot tables
		
		$tableList = $this->db->list_tables();
		
		foreach($tableList as $otherTableName) {
			if(strstr($otherTableName, '_'.$tableName) !== FALSE) {
				$relationshipTableName = substr($otherTableName, 0, -(strlen($tableName) + 1));
				list($tmp, $relationshipColumns) = $this->generateColumnList($relationshipTableName);
				$processedFieldList[$fieldName] = array(
					'type' => 'multi',
					'columns' => $relationshipColumns
				);
			}
		}
		
		return array($processedFieldList, $relationshipFieldList);
	}
	
	protected function formatPHP($arrayRep) {
		$arrayRep = preg_replace('/[ ]{2}/', "\t", $arrayRep);
		$arrayRep = preg_replace("/\=\>[ \n\t]+array[ ]+\(/", '=> array(', $arrayRep);
		return $arrayRep = preg_replace("/\n/", "\n\t", $arrayRep);
		
		$arrayRep = preg_replace('/\)$/', "\t);\n", $arrayRep);
		$arrayRep = str_replace(" \n\t\t", ' ', $arrayRep);
		$arrayRep = preg_replace('/^/', "\t", $arrayRep);
		return $arrayRep;
	}
}
?>