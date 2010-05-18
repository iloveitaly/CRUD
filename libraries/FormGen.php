<?
class FormGen_Core extends Controller {
	public $columns;
	
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
	
	private function isRelationshipField($fieldName) {
		if(substr($fieldName, -2) == $this->relationshipIdentifier) {
			return substr($fieldName, 0, -2);
		} else {
			return FALSE;
		}
	}
}
?>