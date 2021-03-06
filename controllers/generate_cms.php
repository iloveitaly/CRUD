<?
class Generate_Cms_Controller extends Controller {
	const ALLOW_PRODUCTION = FALSE;
		
	protected $get_template = <<<EOL
	
	public function __get(\$key) {%s
		return parent::__get(\$key);
	}

EOL;
	
	public function __construct() {
		parent::__construct();
	}
	
	public function index() { $this->chose(); }
	
	public function chose() {
		$databaseEntries = Kohana::config('database');
		
		foreach($databaseEntries as $dbName => $dbConfig) {
			echo "<h3>Database: {$dbName}</h3>";
			echo "<p><ul>";
			$db = new Database($dbName);
			foreach($db->list_tables() as $tableName) {
				echo "<li>{$tableName}: <a href='".$_SERVER['REQUEST_URI']."/controller/{$dbName}/{$tableName}'>Controller</a> | <a href='".url::current(TRUE)."/model/{$dbName}/{$tableName}'>Model</a></li>";
			}
			
			echo "</p></ul>";
		}
	}
	
	public function model($dbName, $rootTableName) {
		$this->db = new Database($dbName);
		$tableList = $this->db->list_tables();
				
		$ormName = inflector::singular($rootTableName);
		$controllerName = str_replace(' ', '_', ucwords(str_replace('_', ' ', $ormName)));
		
		$outputContent = "<?php\n";
		$outputContent .= "class {$controllerName}_Model extends ORM {\n";

		list($processedFields, $relationshipFields) = $this->generateColumnList($rootTableName);
		
		$hasOne = array();
		$hasMany = array();
		$hasAndBelongsTo = array();

		// generateColumnList will find one-to-one and one-to-many but not many-to-many relationships
		foreach($relationshipFields as $relationshipName => $relationshipInfo) {
			if($relationshipInfo['type'] == 'one') {
				$hasOne[] = "'".$relationshipName."'";
			} else if($relationshipInfo['type'] == 'multi') {
				$hasAndBelongsTo[] = "'".$relationshipName."'";
			}
		}
		
		// search for one-to-many
		
		foreach($tableList as $tableName) {
			// exclude pivot tables
			if(strstr($tableName, inflector::plural($ormName)) !== FALSE) continue;
			
			if(array_search($ormName.'_id', array_keys($this->db->list_fields($tableName))) !== FALSE) {
				$hasMany[] = "'".$tableName."'";
			}
		}
		
		$outputContent .= "\tprotected \$has_many = array(".implode(',', $hasMany).");\n";
		$outputContent .= "\tprotected \$has_one = array(".implode(',', $hasOne).");\n";
		$outputContent .= "\tprotected \$has_and_belongs_to_many = array(".implode(',', $hasAndBelongsTo).");\n";
		
		// search for date range
		// TODO: in the future an __isset should be generated for each of the properties too
		
		$customPropertiesList = array();
		$tableFields = array_keys($processedFields);
		
		if(in_array('start_date', $tableFields) && in_array('end_date', $tableFields)) {
			$outputContent .= "\n\tprotected \$_date_range;\n\n";
			$customPropertiesList[] = <<<EOL
		if(\$key == 'date_range') {
			if(empty(\$this->_date_range))
				\$this->_date_range = new DateRange(\$this->start_date, \$this->end_date);
			return \$this->_date_range;
		}
EOL;
		}

		if(in_array('address1', $tableFields) && in_array('postal_code', $tableFields)) {
			// then we have address information stored in the table
			$customPropertiesList[] = <<<EOL

		if(\$key == 'short_address') { // code w/comments: http://snipplr.com/view.php?codeview&id=46053
			return \$this->name.' in '.\$this->city.' '.\$this->providence.(empty(\$this->city) || empty(\$this->providence) ? ' '.\$this->country : '');
		} else if(\$key == 'full_address') {
			if(\$this->country == 'US') {
				return trim(trim(\$this->address1."\\n".\$this->address2)."\\n".\$this->city).", ".\$this->providence." ".\$this->postal_code;
			} else {
				return trim(trim(\$this->address1."\\n".\$this->address2)."\\n".\$this->city).", ".\$this->providence." ".\$this->postal_code." ".\$this->country;
			}
		}
EOL;
		}
		
		if(count($customPropertiesList) == 0) {
			$outputContent .= "/*".sprintf($this->get_template, '')."*/\n";
		} else {
			$propertyCode = '';
			$l = count($customPropertiesList);
			
			foreach($customPropertiesList as $property) {
				$propertyCode .= $property;
				if(--$l > 0) $propertyCode .= 'else ';
			}
			
			$outputContent .= sprintf($this->get_template, $propertyCode."\n");
		}
		
		$outputContent .= "}\n?>\n";
		
		download::force($ormName.'.php', $outputContent);
	}
	
	public function controller($dbName, $table) {
		$this->db = new Database($dbName);
		$tableList = $this->db->list_tables();
				
		$outputContent = "<?php\n";
		
		// generate the class name
		$controllerName = str_replace(' ', '_', ucwords(str_replace('_', ' ', $table)));
		$outputContent .= "class {$controllerName}_Controller extends CMS_Core {\n\n";
		
		// generate the basic fields
		list($processedFieldList, $relationshipFieldList) = $this->generateColumnList($table);
		
		$outputContent .= "\tpublic \$columns = ";
		$outputContent .= $this->formatPHP(var_export($processedFieldList, true));
		$outputContent .= ";\n";
		
		// generation the relationships
		$outputContent .= "\t\n\tpublic \$relationships = ";
		$outputContent .= $this->formatPHP(var_export($relationshipFieldList, true));
		$outputContent .= ";\n";
		
		// generate basic function wireframe
		$ormName = inflector::singular($table);
		$outputContent .= <<<EOL
	
	function __construct() {
		// how to add a file picker:
		// \$this->createFilePicker('thumbnail', '/path/to/folder', array('jpg', 'jpeg', 'png'))
		
		parent::__construct(__FILE__, '{$ormName}');
		
		\$this->autoRedirect = TRUE;
		\$this->autoAdjustRanking = TRUE;
	}
	
	/*
	public function view() {
		// parent::view(array('add', 'edit', 'delete'))
	}
	*/
	
	/*
	public function edit(\$id = null) {
		// if \$result is true then we are creating or editing a object
		\$result = parent::edit(\$id);
		
		if(\$result) {
			\$mode = \$result['mode'];
			\$page = \$result['data'];
			
			if(\$mode == "create") {
				\$page->date_created = \$page->date_modified = time();
			} else {
				\$page->date_modified = time();
			}
			
			\$page->save();
			
			message::info('Object '.(\$mode == "edit" ? 'Edited' : 'Created').' Successfully.', Kohana::config('admin.base').\$this->controller_name.'/view');
		}
    }
	*/

EOL;
		
		$outputContent .= "}\n?>\n";
		
		download::force(strtolower($controllerName).'.php', $outputContent);
	}
	
	protected function generateColumnList($tableName, $relationshipTable = false) {
		// the generateColumnList method will only be used once in any given session... you can't generate a controller & model at the same time
		// although this is a bit lazy, we can just reset it on first run
		
		if(empty($this->processedTableList)) {
			$this->processedTableList = array();
		}
		
		// check for circular references
		if(!in_array($tableName, $this->processedTableList)) {
			$this->processedTableList[] = $tableName;
		} else {
			return;
		}
		
		$fieldList = $this->db->list_fields($tableName);
		
		$processedFieldList = array();
		$relationshipFieldList = array();
		
		foreach($fieldList as $fieldName => $fieldInfo) {
			// catch the primary ID field and restrict it to view
			if($fieldName == 'id') {
				if($relationshipTable) continue;
				
				$processedFieldList[$fieldName] = array(
					'restrict' => 'view'
				);
				
				continue;
			}
			
			// check if we are a child of a one-to-many or one-to-one relationship
			if(strstr($fieldName, '_id') !== FALSE) {
				// then possibly a one to one relationship field
				$relationshipTableName = inflector::plural(substr($fieldName, 0, -3));
				list($processedRelationshipFields) = $this->generateColumnList($relationshipTableName, true);
				
				$relationshipFieldList[inflector::singular($relationshipTableName)] = array(
					'type' => 'one',
					'columns' => $processedRelationshipFields,
					'manage' => true,
					'display_key' => $this->findDisplayKey($relationshipTableName),
					'auto_adjust_ranking' => true,
					'selection' => 'ajax',
					'search_fields' => array()
				);

				continue;
			}
			
			$processedFieldList[$fieldName] = array();
			
			// handle special cases
			
			if($fieldInfo['type'] == 'int' && strstr($fieldName, 'date') !== FALSE) {
				// then we have an integer based date storage field
				// note that this is a conviention that I use for most of my sites (integer field + date in field name indicates date storage field)
				$processedFieldList[$fieldName]['content'] = 'date';
				$processedFieldList[$fieldName]['class'] = 'datetime';
			} else if($fieldInfo['type'] == 'string' && strstr($fieldName, 'description') !== FALSE) {
				// this is convention, description fields normally require a textarea element in the admin
				$processedFieldList[$fieldName]['type'] = 'textarea';
			} else if($fieldInfo['type'] == 'int' && strstr($fieldName, 'rank') !== FALSE) {
				// rank is not required since in most cases it is auto generated
				$processedFieldList[$fieldName]['required'] = false;
			}
			
			// print_r($fieldInfo);
		}
		
		// look for pivot tables

		$tableList = $this->db->list_tables();
		
		foreach($tableList as $otherTableName) {
			// I think the merging order (tabla vs tableb) is determined alphabetically
			// since we only know one side of the equation we have to try both combinations
			
			if(strstr($otherTableName, '_'.$tableName) !== FALSE || strstr($otherTableName, $tableName.'_') !== FALSE) {
				// pivot tables are always structured as item_categories_items
				if(strstr($otherTableName, '_'.$tableName) !== FALSE) {
					$relationshipTableName = substr($otherTableName, 0, -(strlen($tableName) + 1));
				} else {
					// then the table that is being examined is on the the left of the pivot table name
					$relationshipTableName = substr($otherTableName, -(strlen($otherTableName) - strlen($tableName)) + 1);
				}
				
				if($relationshipTableName == $tableName) {
					echo "LOOP:<br/>".$relationshipTableName."<br/>";
					echo $tableName."<br/>".$otherTableName;
					exit();
				}
				
				// since this *could* result in a rescursive function (for a pivot table) we have to 
				list($tmp, $relationshipColumns) = $this->generateColumnList($relationshipTableName);

				$relationshipFieldList[$relationshipTableName] = array(
					'type' => 'multi',
					'manage' => true,
					'columns' => $relationshipColumns,
					'display_key' => $this->findDisplayKey($relationshipTableName),
					'auto_adjust_ranking' => true
				);
			}
		}
		
		// note that we don't look for one-to-many relationships here
		// only the child needs to have a one-relationship in the CMS to properly allow the user to edit one-to-many relationships
		
		return array($processedFieldList, $relationshipFieldList);
	}
	
	protected function findDisplayKey($relationshipTable) {
		$fallBack = '';
		
		foreach($this->db->list_fields($relationshipTable) as $fieldName => $fieldInfo) {
			if($fieldInfo['type'] == 'string') {
				$fallBack = $fieldName;
			}
			
			if(strstr($fieldName, 'name') !== FALSE || strstr($fieldName, 'title') !== FALSE)
				return $fieldName;
		}
		
		return $fallBack;
	}
	
	protected function formatPHP($arrayRep) {
		$arrayRep = preg_replace('/[ ]{2}/', "\t", $arrayRep);
		$arrayRep = preg_replace("/\=\>[ \n\t]+array[ ]+\(/", '=> array(', $arrayRep);
		return $arrayRep = preg_replace("/\n/", "\n\t", $arrayRep);
	}
}
?>