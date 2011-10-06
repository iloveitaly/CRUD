<?
class FormGen_Core extends Controller {
	public $columns;
	public $form;
	public $relationships;
	
	protected $filteredColumns;
	public $objectReference;		// reference to the object being edited. Most likely this will be an ORM model but in cases on a form that is not associated with a database $objectReference can simply be an array
	
	public $errors;
	public $base_config;
	public $orm_name;
	
	// allow display customization
	public $form_class = 'hform';
	public $form_action = '';			// auto set in the constructor, specify if using in a non CMS solution
	public $form_name = '';				// if form_name isn't set then the orn_name is used for the <form name>
	public $form_style = '';
	
	public $fields_template = 'fields';	// template to use for field generation
	public $edit_template = 'form';
	public $view_template = 'view';
	
	protected $relationshipIdentifier = '[]';
	
	/*
		$data_holder = array(
			'columns' => array of column data
			'base_config => array(
				'submit_title' => 'title of the submit button,
				'form_class' => 'sform',
				'form_title' => title of the fieldset
			)
 		)
	*/
	
	function __construct($data_holder) {
		parent::__construct();
		
		// if you want to overide any of the values in base_config in a super-super class (the class subclassing CMS) this may cause problems
		// note that although orm_name is 'used' in this class it is NOT required, it is only referenced to have built in support for CRUD
		
		$data_holder = is_array($data_holder) ? (object) $data_holder : $data_holder;
		
		// if no columns are set, assume that the data_holder contains the columns
		if(!isset($data_holder->columns)) {
			$data_holder = (object) array(
				'columns' => (array) $data_holder
			);
		}
		
		$this->columns = $data_holder->columns;
		$this->errors = array();
		$this->relationships = array();
		$this->base_config = & $data_holder->base_config;	// there is no need to copy the array, referencing it allows more flexibility
		
		// if the config is empty set some default values
		if(empty($this->base_config)) {
			$this->base_config = array(
				'submit_title' => 'Submit',
			);
		} else {
			// copy some of the special base_config options
			if(!empty($this->base_config['form_class'])) $this->form_class = $this->base_config['form_class'];
		}
		
		// note that base_config should contain the following:
		//	title or submit_title (note that title is auto generated by the CMS using the orm name and titlize)
		
		$this->normalizeColumnOptions();
	}
	
	public function generateWebRequest() {
		// get a list of all keys used in the current submission, sort them, and create a unique signature for the submission
		$formData = (array) $this->objectReference;
		$keyList = array_keys($formData);
		asort($keyList);
		$uniqueKey = md5(http_build_query($keyList));
		
		// grab the request object to tie this request too
		$requestPath = Router::$controller.'/'.Router::$method;
		$requestType = ORM::factory('website_request_type')->where('key', $uniqueKey)->find();
		
		if(!$requestType->loaded) {
			$requestType = ORM::factory('website_request_type');
			$requestType->path = $requestPath;
			$requestType->key = $uniqueKey;
			$requestType->field_list = http_build_query(array_intersect_key($this->columns, $formData));
			$requestType->save();
		}
		
		$websiteRequest = ORM::factory('website_request');
		$websiteRequest->website_request_type_id = $requestType;
		$websiteRequest->date = gmmktime();
		$websiteRequest->data = http_build_query($formData);
		
		return $websiteRequest;
	}
	
	public function generateEmailMessage($html = false, $excludeList = array()) { return $this->generate_email_message($html, $excludeList); }
	public function generate_email_message($html = false, $excludeList = array()) {
		$post = $this->input->post();
		$message = '';
		
		if($html) {
			$message .= <<<EOL
<style type="text/css" media="screen">
	th {
		text-align:left;
	}
</style>
<table>
EOL;
		}

		// this could be a bit more advanced: question --> answer inflections, i.e.:
		// How many people at parish? --> People at parish:
		
		$lineEnding = (!$html ? "\n" : '');

		foreach($this->columns as $columnName => $columnInfo) {
			if($columnInfo['restrict'] == 'view') continue;
			if(in_array($columnName, $excludeList) === TRUE) continue;
			
			if(isset($columnInfo['email']) && !$columnInfo['email']) {
				continue;
			}
			
			// if you want to hide a custom element from the email generation set restrict = view
			if($columnInfo['type'] == 'custom') {
				// skip fields where columnInfo = FALSE
				if($html) {
					$message .= "<tr><td colspan=\"2\" align=\"center\">";
				}
			} else {
				$columnDisplayName = empty($columnInfo['label']) ? inflector::titlize($columnName) : $columnInfo['label'];
				
				if($html) {
					$message .= "<tr><th width='25%'>".$columnDisplayName."</th><td>";
				} else {
					$message .= $columnDisplayName;
					$message .= ctype_punct($columnDisplayName[strlen($columnDisplayName) - 1]) ? ' ' : ': ';
				}
			}
			
			if(empty($post[$columnName]) && empty($this->objectReference->$columnName) && $columnInfo['type'] != 'custom' && !$this->isRelationshipField($columnName)) {
				$message .= "Empty".(!$html ? "\n" : '');
			} else switch($columnInfo['type']) {
				case 'select':
					$flipped = array_flip($columnInfo['values']);
					$message .= $flipped[$this->objectReference->$columnName].$lineEnding;
					break;
				case 'mselect':
					$strippedColumnName = substr($columnName, 0, -strlen($this->relationshipIdentifier));
					$rawValueList = $this->objectReference->$strippedColumnName;
					$formattedValueList = array();
					$flippedValueList = array_flip($columnInfo['values']);
					foreach($rawValueList as $rawValue) {
						$formattedValueList[] = $flippedValueList[$rawValue];
					}
					
					$message .= human_list($formattedValueList);
					break;
				case 'checkbox':
					// if we are processing a checkbox they could select multiple options
					// formo returns a list of the keys in the values list, we have to grab the label values associated with each key
					
					if(is_array($this->form->$columnName->value)) {
						$convertedList = array_values($this->form->$columnName->value);
						$message .= implode(', ', $convertedList).$lineEnding;
					} else {
						$message .= $this->form->$columnName->value.$lineEnding;
					}
					
					break;
				case 'custom':
					if($html) {
						$message .= "<h2>".str_replace(array(':'), '', $columnInfo['label'])."</h2>";
					} else {
						$message .= "\n".preg_replace('#</?h[1-9]>|</?b>#', ' --- ', str_replace(array(':'), '', $columnInfo['label']))."\n\n";
					}
					break;
				case 'file':
					$uploadedFilePath = join_paths(url::base(), $columnInfo['upload_path'], $this->objectReference->$columnName);
					
					if($html) {
						$message .= "<a href=\"".$uploadedFilePath."\">Download File</a>";
					} else {
						$message .= $uploadedFilePath."\n";
					}
					
					break;
				default:
					$message .= $post[$columnName].(!$html ? "\n" : '');
			}
			
			if($html) $message .= "</td></tr>";
		}
		
		if($html) $message .= "</table>";
		
		return $message;
	}
	
	// returns true if the post data validated against the form
	
	public function process(& $ref = null) {
		$this->objectReference = $ref ? $ref : new stdClass();
		$this->filteredColumns = array();
		$this->form = Formo::factory($this->form_name ? $this->form_name : $this->orm_name)
			->set('action', $this->form_action)
			->set('style', $this->form_style)
			->set('class', $this->form_class);
		
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
				if(!empty($options['values'])) {
					$this->form->add_group($columnName.$this->relationshipIdentifier, $options['values']);
				} else {
					$this->form->add($columnInfo['type'], $columnName, $options);
				}
				
				// for some reason we have to manually assign label... bug?
				// this label is displayed in the error 
				$this->form->$columnName->label = $options['label'];
			} else if($columnInfo['type'] == 'radio') {
				$this->form->add_group($columnName, $options['values']);
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
			$copyFieldList = is_subclass_of($this->objectReference, "ORM_Core") ? $this->objectReference->table_columns : $this->filteredColumns;
			
			foreach(array_merge($relationshipCopyList, $copyFieldList) as $columnName => $columnInfo) {
				// Be nice and don't cause errors trying to copy over data to columns that don't exist
				if(!isset($this->filteredColumns[$columnName])) continue;
				if($this->filteredColumns[$columnName]['type'] == 'custom' && !isset($this->form->$columnName)) continue;
				
				if($this->filteredColumns[$columnName]['type'] == 'file') {
					// use uploaded file name rather than the original file name
					if(isset($this->form->$columnName->data['file_name'])) { // this is for when the file name isn't required
						$this->objectReference->$columnName = $this->form->$columnName->data['file_name'];
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
		
	public function generate() {
		$compiledForm = $this->form->get(TRUE);
		$compiledFields = View::factory($this->fields_template, array(
			'columns' => $this->filteredColumns,
			'form' => $compiledForm
		));

		return View::factory($this->edit_template, array_merge($this->base_config, array(
			'form' => $compiledForm,	// for the open/close form tags
			'fields' => $compiledFields,
			'errors' => array_merge($this->form->get_errors(), $this->errors)
		)));
	}
	
	protected function _getOptions($columnName, $columnData, $page) {
		// values is an array of options to be sent to formo->add
		$values = array('required' => 1);
		
		// the $page should have precedent over predefined constants ONLY if it was loaded
		
		if(is_subclass_of($page, "ORM_Core") && $page->loaded && isset($page->$columnName)) {
			$values['value'] = $page->$columnName;
		} else if(isset($columnData['value'])) {
			$values['value'] = $columnData['value'];
		}
		
		$accessField = $this->isRelationshipField($columnName);
		
		// the __isset function of ORM has a little bug
		// for one-to-many relationships if the variable is not attempted to be retrieved first
		// then the variable is reported as !set, so we try to recieve it if $accessField is true
		if($accessField)
			@$page->$accessField;
		
		// sometimes we have 'fake' multiple selects that pass at a relationship field
		// checking to make sure the $accessField is set prevents a undefined variable error
		if($accessField && isset($page->$accessField)) {
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
		$copyList = array('style', 'class', 'checked', 'required', 'label', 'placeholder', 'autocomplete', 'multiple', 'allowed_types', 'max_size', 'upload_path', 'rule', 'error_msg');
		
		foreach($copyList as $attrib) {
			if(isset($columnData[$attrib])) {
				$values[$attrib] = $columnData[$attrib];
			}
		}
		
		// if the required field is set auto set class='required' if not explicitly defined
		if($values['required']) {
			if(!isset($values['class'])) $values['class'] = 'required';
			else $values['class'] .= ' required';
		}
		
		// make the labels for the form fields look nice
		// < 3 => probably an abbreviation
		if(!isset($values['label'])) {
			$values['label'] = inflector::titlize($columnName);
		}
		
		if(strrpos($columnData['type'], 'select') !== FALSE ||
		   $columnData['type'] == 'checkbox' ||
		   $columnData['type'] == 'radio'
		) {
			// check to make sure values is an array, if not it will cause issues in select library
			if($columnData['type'] == 'checkbox' && (empty($columnData['values']) || !is_array($columnData['values']))) {
				Kohana::log('error', 'CRUD form generator found select values error for field '.$columnName);
			} else {			
				// this is for convience, it will automatically generate computer values if we have a non-assoc array of values
				
				if(!array_is_assoc($columnData['values'])) {
					$optionValues = array_values($columnData['values']);
					$optionKeys = array();
				
					foreach($optionValues as $value)
						$optionKeys[] = inflector::computerize($value);
			
					$values['values'] = array_combine($optionKeys, $optionValues);
				} else {
					$values['values'] = $columnData['values'];
				}
			}
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
	
	protected function normalizeColumnOptions() {
		
		// normalize the column array
		// 	default type: text
		//	default restrict: none
		//	default content: text
		
		// check for a flat array and convert to a associated array with name/key => blank array/value
		// this is just for conveince
		if(!array_is_assoc($this->columns)) {
			$associatedColumns = array();

			foreach($this->columns as $key => $columnName) {
				$associatedColumns[$columnName] = array();
			}
			
			$this->columns = $associatedColumns;
		}

		foreach($this->columns as $name => $columnInfo) {			
			if(!isset($columnInfo['type'])) {
				$this->columns[$name]['type'] = 'text';
			}
			
			if(!isset($columnInfo['restrict'])) {
				$this->columns[$name]['restrict'] = 'none';
			}
			
			if(!isset($columnInfo['content'])) {
				$this->columns[$name]['content'] = 'text';
			}
			
			if(!isset($columnInfo['format'])) {
				$this->columns[$name]['format'] = '';
			}
		}
	}
}
?>