<?php
class Crud_Core extends FormGen_Core {
	public $relationshipSearchFieldSuffix = '_search';
	public $relationshipViewFieldSuffix = '_id_v';
	
	function __construct($data_holder) {
		parent::__construct($data_holder);
		
		$data_holder = is_array($data_holder) ? (object) $data_holder : $data_holder;
		
		// Note that CMS uses this object as a sort of core module
		// It passes off the column names & relationships and that is about it, no more column managment is done by CMS
		
		$this->relationships = isset($data_holder->relationships) ? $data_holder->relationships : array(); // for direct (non CMS) use Crud, we might not always need relationships
		$this->orm_name = $data_holder->orm_name;
		
		// add relationships to the column list, we have to generate content functions & attempt to guess default values
		// note that relationships != checkbox / radio groups. The relationship functionality was built for database relationships
		// the $name should be the relationship field in the source database (i.e. the study_category_id field's $name should be study_category)
		
		foreach($this->relationships as $name => $relationshipInfo) {
			// the tricky label code strips out the commonality between a category listing & the relationship, ex:
			//	news_item_categories
			//	news_items
			//	resulting nlabel: Categories
			
			// note that the orm_name will be the singular of the plural db name in many-to-many (or one-to-many) database structures
			if(empty($relationshipInfo['label'])) {
				$relationshipLabel = starts_with($this->orm_name, $name) ? inflector::titlize(substr($name, strlen($this->orm_name))) : inflector::titlize($name);
			} else {
				$relationshipLabel = $relationshipInfo['label'];
			}
			
			// default type to multi (many-to-many)
			// default restrict to none
			
			if(empty($relationshipInfo['type'])) $relationshipInfo['type'] = 'multi';			// one or multi
			if(empty($relationshipInfo['restrict'])) $relationshipInfo['restrict'] = 'none';	// view or edit
			if(empty($relationshipInfo['selection'])) $relationshipInfo['selection'] ='html';	// html or ajax
			if(!isset($relationshipInfo['manage'])) $relationshipInfo['manage'] = true;
			
			if($relationshipInfo['type'] == 'one') {
				// for one-to-one we don't use a pivot table
				// convention is to use: singular_table_id
				
				// _v = view field
				$this->columns[$name.$this->relationshipViewFieldSuffix] = array(
					'label' => $relationshipLabel,
					'type' => 'custom',
					'content' => create_function('$ob', 'return ORM::factory("'.inflector::singular($name).'", $ob->'.$name.'_id)->'.$relationshipInfo['display_key'].';'),
					'restrict' => 'view'
				);
				
				if($relationshipInfo['restrict'] != 'view') {
					// this isn't a perfect method since there can be duplicate display_keys and then only one displays
					
					if($relationshipInfo['selection'] == 'html') {
						$this->columns[$name.'_id'] = array(
							'label' => $relationshipLabel,
							'type' => 'select',
							'values' => ORM::factory(inflector::singular($name))->select_list($relationshipInfo['display_key'], 'id'),
							'restrict' => 'edit'
						);
					} else {
						// configure ajax selection
					
						$this->columns[$name.'_id'] = array(
							'label' => $relationshipLabel,
							'type' => 'hidden',
							'restrict' => 'edit'
						);
					
						$this->columns[$name.$this->relationshipSearchFieldSuffix] = array(
							'label' => $relationshipLabel,
							'restrict' => 'edit',
							'required' => 0
						);
					}
				}
			} else { // many
				// for the view
			
				$this->columns[$name] = array(
					'restrict' => 'view',
					'label' => $relationshipLabel,
					'type' => 'custom',
					'content' => create_function('$arg', '$str = ""; foreach($arg->'.$name.' as $rel) {$str .= $rel->'.$relationshipInfo['display_key'].'."<br />";} return $str;')
				);
						
				// for the edit field

				$this->columns[$name.$this->relationshipIdentifier] = array_merge($relationshipInfo, array(
					'restrict' => 'edit',
					'label' => $relationshipLabel,
					'type' => 'mselect',
					'values' => ORM::factory(inflector::singular($name))->select_list($relationshipInfo['display_key'], 'id')
				));
			}
			
			$this->relationships[$name] = $relationshipInfo;
		}
		
		// since we've added some relationship columns we have to run the normalization again
		$this->normalizeColumnOptions();
		
		// this works in most CMS situations (editing controller/edit/id, the id is then passed to edit())
		// however in form generation situations we may want to define a custom action
		
		$this->form_action = Kohana::config('admin.base').url::current();
	}
	
	public function view() {
		// filter out edit only columns
		$columnNames = array();
		
		foreach($this->columns as $name => $columnInfo)
			if(!isset($columnInfo['restrict']) || $columnInfo['restrict'] != 'edit')
				$columnNames[$name] = $columnInfo;
		
		return $columnNames;
	}
	
	public function edit($id = null) {
		// there are three 'modes' of this function: view, edit, and create
		//	if we aren't editing or creating we are viewing
		//	When you are in the process of editing a object you are NOT editing it.
		
		$page = ORM::factory($this->orm_name, (int) $id);
		
		if(!$page->loaded) {// creating / viewing
			$mode = "create";
			$page = ORM::factory($this->orm_name);
		} else { // editing
			$mode = "edit";
			$page = ORM::factory($this->orm_name, (int) $id);
		}
		
		if($this->process($page)) {			
			return array('mode' => $mode, 'data' => $page);
		} else {
			// you overide this title by accessing $this->template->content->submit_title in the subclass
			
			if(empty($this->base_config['submit_title'])) {
				if($mode == "edit") {
					$this->base_config['submit_title'] = 'Save Changes';
				} else {
					$this->base_config['submit_title'] = 'Create New '.$this->base_config['title'];
				}
			} else {
				$submitTitle = $this->base_config['submit_title'];
			}
			
			// handle ajax one-to-one relationship value display 
			foreach($this->relationships as $name => $relationshipInfo) {
				if($relationshipInfo['restrict'] != 'view' && $relationshipInfo['selection'] == 'ajax') {
					$relationshipDisplayFieldName = $name.$this->relationshipSearchFieldSuffix;
					$relationshipIDColumn = $name.'_id';
					
					// to generate the display value we implode the selected search keys
					$this->form->$relationshipDisplayFieldName->value = implode_with_keys(' ', (array) ORM::factory($name, $this->form->$relationshipIDColumn->value)->as_array(), $relationshipInfo['search_fields']);
				}
			}
			
			return array('mode' => 'view', 'data' => $this->generate());
		}
    }
	
	public function delete($id = null) {
		$post = ORM::factory($this->orm_name, (int) $id);

		if(!$post->loaded) return false;
		
		// maybe do some more error checking here
		$post->delete();
		
		return true;
	}	
}

?>