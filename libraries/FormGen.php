<?
class FormGen_Core extends Controller {
	public $columns;
	protected $filteredColumns;
	protected $objectReference;
	
	protected $relationshipIdentifier = '[]';
	
	public function generate_email_message() {
		$post = $this->input->post();
		$message = '';
		
		foreach($this->columns as $columnName => $columnInfo) {
			// we skip custom (aka headers / title blocks) and 'display only' elements
			if($columnInfo['type'] == 'custom') continue;
			if($columnInfo['restrict'] == 'view') continue;
			
			$message .= (empty($columnInfo['label']) ? inflector::titlize($columnName) : $columnInfo['label']).": ";
			
			if($columnInfo['type'] == 'checkbox') {
				// if we are processing a checkbox they could select multiple options
				// formo returns a list of the keys in the values list, we have to grab the label values associated with each key
				
				$convertedList = array();
				foreach($post[$columnName] as $key) {
					// we strip tags here b/c it is possible to embed sub fields within the text of a checkbox label
					$convertedList[] = strip_tags($this->columns[$columnName]['values'][$key]);
				}
				
				$message.= implode(', ', $convertedList)."\n";
			} else {
				$message .= $post[$columnName]."\n";
			}
		}
		
		return $message;
	}
	
	// returns true if the post data validated against the form
	public function generate($ref = null) {
		$this->objectReference = $ref ? $ref : new Object();
		$this->filteredColumns = array();
		$this->form = Formo::factory($this->form_name ? $this->form_name : $this->orm_name)
			->set('action', $this->form_action)
			->set('_class', $this->form_class);
		
		// looks like formo strtolowers the $columnName
		// this means that your dbs must use field_name instead of FieldName or you will get an undefined index error
		
		/*
			There are a couple custom types:
				
				'custom' - a header or some other custom HTML, this is a special case in the fields.php view.
				Note that custom fields ARE not auto included in email generation or other cases
				
				'view' - used mainly in the CRUD use case. This is for generating a column which only displays when viewing all the data
		*/
		
		foreach($this->columns as $columnName => $columnInfo) {
			if($columnInfo['restrict'] == "view") continue;
			
			$this->filteredColumns[$columnName] = $columnInfo;
			
			if($columnInfo['type'] == 'custom') continue;
			
			$options = $this->_getOptions($columnName, $columnInfo, $this->objectReference);
			
			if($columnInfo['type'] == 'checkbox') {
				$this->form->add_group($columnName.$this->relationshipIdentifier, $options['values']);
				
				// for some reason we have to manually assign label... bug?
				// this label is displayed in the error 
				$this->form->$columnName->label = $options['label'];
			} else {
				$this->form->add($columnInfo['type'], $columnName, $options);
			}
		}

		if($this->form->validate() && count($this->errors) == 0) {
			// this adds the relationship identifier ([]) to the relationship names and merges the list with the column name list
			// because the relationship_name is copied over to $columnNames as relationship_name[] during initialization
			// we have to do add the suffix and then merge the columns since these columns do not exist in the actual table schema
			
			$relationshipCopyList = array_fill_keys(array_add_value_suffix(array_keys($this->relationships), $this->relationshipIdentifier), 'relationship');
			$copyFieldList = isset($this->objectReference->table_columns) ? $this->objectReference->table_columns : $this->filteredColumns;
			
			foreach(array_merge($relationshipCopyList, $copyFieldList) as $columnName => $columnInfo) {
				// Be nice and don't cause errors trying to copy over data to columns that don't exist
				if(!isset($this->filteredColumns[$columnName])) continue;

				if($this->filteredColumns[$columnName]['type'] == 'file') {
					// use uploaded file name rather than the original file name
					
					if(isset($form->$columnName->data['file_name'])) { // this is for when the file name isn't required
						$this->objectReference->$columnName = $form->$columnName->data['file_name'];
					} else {
						Kohana::log('debug', 'File data not submitted');
					}
				} else if(substr($columnName, -2) == $this->relationshipIdentifier) {
					// multiple values isn't really natively supported in formo
					// we have to go directly into the post data and retrieve the list
					
					$normalizedRelationshipName = substr($columnName, 0, -2);
					$post = $this->input->post();
					$this->objectReference->$normalizedRelationshipName = $post[$normalizedRelationshipName];
				} else {
					$this->objectReference->$columnName = $this->form->$columnName->value;
				}
			}
			
			return true;
		}
		
		return false;
	}
	
	protected function _getOptions($columnName, $columnData, $page) {
		// values is an array of options to be sent to formo->add
		$values = array('required' => 1);
		
		// the $page should have precedent over predefined constants ONLY if it was loaded
		
		if($page->loaded && isset($page->$columnName)) {
			$values['value'] = $page->$columnName;
		} else if(isset($columnData['value'])) {
			$values['value'] = $columnData['value'];
		}
		
		if($accessField = $this->isRelationshipField($columnName)) {
			// then we are dealing with a relationship

			if($columnData['type'] == 'mselect') {
				// get a list of IDs
				$primaryKey = $page->primary_key;
				$selectedList = iterator_to_array($page->$accessField);
				$idList = array();
				
				foreach($selectedList as $relatedObject) {
					$idList[] = $relatedObject->$primaryKey;
				}

				$values['selected_values'] = $idList;
			} else if($columnData['type'] == 'select') {
				
			}
		}
		
		// this is a list of fields to be copied over as attributes (unless they are 'special' fields) of the HTML element
		$copyList = array('style', 'class', 'required', 'label', 'multiple', 'allowed_types', 'max_size', 'upload_path', 'rule', 'error_msg');
		
		foreach($copyList as $attrib) {
			if(isset($columnData[$attrib])) {
				$values[$attrib] = $columnData[$attrib];
			}
		}
		
		// if the required field is set auto set class='required' if not explicitly defined
		if($values['required'] && !isset($values['class'])) {
			$values['class'] = 'required';
		}
		
		// make the labels for the form fields look nice
		// < 3 => probably an abbreviation
		if(!isset($values['label'])) {
			$values['label'] = inflector::titlize($columnName);
		}
		
		if(strrpos($columnData['type'], 'select') !== FALSE || $columnData['type'] == 'checkbox') {
			// check to make sure values is an array, if not it will cause issues in select library
			if(!is_array($columnData['values'])) Kohana::log('error', 'CRUD form generator found select values error for field '.$columnName);
			
			$values['values'] = $columnData['values'];
		}
		
		return $values;
	}
	
	protected function isRelationshipField($fieldName) {
		if(substr($fieldName, -2) == $this->relationshipIdentifier) {
			return substr($fieldName, 0, -2);
		} else {
			return FALSE;
		}
	}
}
?>