<?php
class CMS_Core extends Template_Controller {
	public $template = "main";
	
	public $controller_name;
	public $base_config;
	public $relationship_controllers;
	public $columns = array();
	
	/*
		Relationships define a many to many relationship
		Essentially a one to many relationship is just a many to many relationship
		where one thing is only relating to one other thing.
		
		'relationship_name' => array(
			'display_key' => 'name',
			'value' => 1
		)
		
	*/
	public $relationships = array();
	
	protected $createDefaults = array();	// defaults to be copied over when an object is created
	protected $editDefaults = array();		// default to be copied over when an object is edited
	protected $autoRedirect = FALSE;		// auto redirect user, useful for if you don't want to write redirect code in your subclass
	public $autoAdjustRanking = false;
	protected $crud;
	
	function __construct($file_path, $orm_name, $columns = array(), $relationships = array()) {
		parent::__construct();

		$this->relationship_controllers = array();
		$this->orm_name = $orm_name;
		$this->controller_name = basename($file_path, '.php');	// the $file_path passed should be __FILE__ of the subclass. You can also simply pass the controller name
		$this->base_config = array(
			'title' => inflector::titlize($this->orm_name),
			'action_url' => Kohana::config('admin.base').$this->controller_name."/"
		);
		
		// check if user is logged in or not. also check if he has admin role
		// you can make some methods public, just define $this->public_methods as an array with method names you want to be public
		
		if(!isset($this->public_methods) || array_search(Router::$method, $this->public_methods) === FALSE) {
			if(!Auth::factory()->logged_in('admin')) {
				Session::instance()->set('redirect_me_to', url::current());
				url::redirect(Kohana::config('admin.base'));
			}
		}
		
		$this->template->title = $this->base_config['title'];
		$this->template->header = View::factory('header');
		
		$this->template->head = Head::instance();
		$this->template->head->javascript->append_file(Kohana::config('admin.js'));
		$this->template->head->css->append_file(Kohana::config('admin.css'));
		$this->template->head->title->set(Kohana::config('admin.title').$this->base_config['title']);
		
		// allow the columns & relationships to be passed as an argument
		
		if(count($columns) > 0)
			$this->columns = $columns;
		
		if(count($relationships) > 0)
			$this->relationships = $relationships;
			
		// setup crud assistant
		$this->crud = new Crud($this);
		
		// check if there is a text area, if so install CKEditor
		// only check for text editors when we are not in edit mode
		
		if(Router::$method != 'view' && Router::$method != 'index') {
			$editorAdded = false;
			$datePickerAdded = false;
			$domReadyJavascript = '';
			
			foreach($this->crud->columns as $columnName => $columnInfo) {
				if($columnInfo['type'] == 'textarea' && !$editorAdded) {
					$this->template->head->javascript->append_file('/ckeditor/ckeditor.js');
					$domReadyJavascript .= '
var target = $$(".editor");

if(target.length > 0) {
	target = target[0];
	
	// remove the label
	$$("label[for=\'" + target.getProperty("id") + "\']")[0].destroy();
	
	CKEDITOR.replace(target, {
		//width:"730px",
		height:"500px"
	});
}
					';
					
					$editorAdded = true;
				}
				
				if($columnInfo['content'] == 'date' && !$datePickerAdded) {
					$domReadyJavascript .= '
new DatePicker(".'.$columnInfo['class'].'", {
	format: "'.Kohana::config('admin.date_format').'",
	pickerClass: "datepicker_dashboard",
	allowEmpty: true
});
					';
					
					$datePickerAdded = true;
				}
				
				if(strstr($columnName, $this->crud->relationshipSearchFieldSuffix) !== FALSE) {
					// this auto-generates the JS code for a one-to-one relationship 
					// CRUD generates two fields: base_name + '_id' & base_name + search field suffix
					// base name is the name of the relationship without the '_id'
					// the field with the suffix acts as the user visible field while the id of the element chosen is stored in the hidden field
					// which is then copied into the ORM object when the edit action is being handled
					// the search queries are handled by $this->search() and return a json object with two fields: display_name & id
					
					$baseColumnName = substr($columnName, 0, strlen($columnName) - strlen($this->crud->relationshipSearchFieldSuffix));
					$domReadyJavascript .= "
new Autocompleter.Request.JSON('{$columnName}', '".$this->base_config['action_url']."search/".$baseColumnName."', {
	postVar: 'search',
	selectMode: false,
	minLength: 3,
	width: 'auto',
	injectChoice:function(token) {
		var choice = new Element('li', {'html': this.markQueryValue(token['display_name'])});
		choice.inputValue = token['display_name'];
		choice.inputData = token;
		this.addChoiceEvents(choice).inject(this.choices);
	},
	onSelection:function(element, selected, selection) {
		$('{$baseColumnName}_id').set('value', selected.inputData['id']);
	}
});
";
				}
			}
			
			if(!empty($domReadyJavascript))
				$this->template->head->javascript->append_script('window.addEvent("domready", function() {'.$domReadyJavascript.'});');
		}
		
		// setup some common defaults
		
		$this->createDefaults = array(
			'date' => gmmktime()
		);
	}
	
	function index() {
		$this->view();
	}
	
	public function view($options = array('edit', 'delete', 'add'), $query_object = null) {
		$columnNames = $this->crud->view();
		$db = Database::instance();
		$rpp = Kohana::config('pagination.default.items_per_page');
		
		// two sorting keys: s => sort key, d => sort direction
		$query = $this->input->get();
		
		if(isset($query['s']) && isset($query['d'])) {
			$orderField = $query['s'];
			$orderDirection = $query['d'] == 'a' ? 'ASC' : 'DESC';
		} else {
			$orderField = 'id';
			$orderDirection = 'ASC';
		}
		
		if(!$query_object) {
			$query_object = ORM::factory($this->orm_name);
		}
		
		// this saves the current ORM statement that could of been created via query object
		// the count_records() resets the query object and looses the restriction query
		// since the query is a restriction many we should use that restriction to handle pagination?
		// would be a little tricky...
		
		$db->push();
		
		$pagination = new Pagination(array(
			'base_url' =>  Kohana::config('admin.base').$this->controller_name.'/',
			'style' => "classic",
			'total_items' => (int) $db->count_records(inflector::plural($this->orm_name)),
			'items_per_page' => $rpp
		));
		
		// pull back the the restriction query
		$db->pop();
		
		$result = $query_object->orderby($orderField, $orderDirection)->limit($rpp, $pagination->sql_offset)->find_all();

		$this->template->content = View::factory('view', array_merge($this->base_config, array(
			'entries' => $result,
			'columns' => $columnNames,
			'relationships' => $this->crud->relationships,
			'options' => $options,
			'pages' => $pagination->render('digg')
		)));
	}
	
	public function edit($id = null) {
		// you can overide this function in your controller
		// if edit() returns false we 'viewing' the creation of a new one, the user can enter in the information
		// if edit() returns an array ('mode', 'data') then we are creating/editing
		// you can determine the editing/creating mode via 'mode' key
		
		$result = $this->crud->edit($id);
		
		if($result['mode'] == 'view') {
			$this->template->content = new View('edit', array_merge($this->base_config, array(
			    'page_title' => ($id != null ? 'Edit ' : 'Create ').$this->base_config['title']
			)));
			
			$this->template->content->form = $result['data'];
			
			return false;
		} else {
			// handle default copying
			$keyList = array_keys($result['data']->table_columns);
			
			foreach($result['mode'] == 'edit' ? $this->editDefaults : $this->createDefaults as $key => $value) {
				if(in_array($key, $keyList)) {
					$result['data']->$key = $value;
				}
			}
			
			if($this->autoAdjustRanking && isset($this->columns['rank'])) {
				if($result['mode'] == 'edit') {
					$this->adjustRankOrdering(ORM::factory($this->orm_name)->find_all(), $result['data']);
				} else {
					$max = (int) ORM::factory($this->orm_name)->select('MAX(rank) as max')->orderby('rank' ,'DESC')->find()->max + 1;					
					$result['data']->rank = $max;
				}
			}
			
			if($this->autoRedirect) {
				$result['data']->save();
				message::info(inflector::titlize(inflector::singular($this->controller_name)).' '.ucwords($result['mode']).'ed', $this->base_config['action_url']);
			}

			return $result;
		}		
    }

	public function delete($id = null) {
		if($this->crud->delete($id)) {
			message::info(inflector::titlize($this->controller_name).' Successfully Deleted', $this->base_config['action_url']);
		} else {
			message::error('Invalid ID', $this->base_config['action_url']);
		}
	}
	
	public function search($searchName) {
		$this->auto_render = FALSE;
		$post = $this->input->post();
		$emptyMessage = array(array('display_name' => 'No Results Found', 'id' => ''));
		
		if(empty($post['search']) || empty($this->crud->relationships[$searchName])) {
			exit(json_encode($emptyMessage));
		}
				
		$search = $post['search'];
		$results = ORM::factory($searchName)->like($this->crud->relationships[$searchName]['display_key'], $search)->find_all();
		$processedResults = array();

		foreach($results as $result) {
			$processedResults[] = array(
				'display_name' => implode_with_keys(' ', (array) $result->as_array(), $this->crud->relationships[$searchName]['search_fields']),
				'id' => $result->id
			);
		}
		
		if(empty($processedResults)) {
			echo json_encode($emptyMessage);
		} else {
			echo json_encode($processedResults);
		}
	}
	
	// $group:		Elements in the rank group
	// $adjusted:	The element with the adjusted rank (note that $adjusted must have the adjusted rank & $group must contain the non-adjusted version of $adjusted)
	protected function adjustRankOrdering($group, $adjusted) {
		// determine which direction the adjustment is being made and then push / pull the ranking on forward / prev items accordingly
		
		$newRank = $adjusted->rank;
		$oldRank = -1;
		
		// find the original
		foreach($group as $item) {
			if($item->id == $adjusted->id) {
				$oldRank = $item->rank;
				break;
			}
		}
		
		if($newRank == $oldRank) return;
		
		// 1 = decreasing (so the current ranked element should be increased), -1 = increasing (so the current ranked element should be decreased)
		$direction = $newRank - $oldRank > 0 ? -1 : 1;
		
		foreach($group as $item) {
			// find the element in the group with the new ranking choice and swap positions 
			if($item->rank == $newRank) {
				// then someone has our ranking place
				$item->rank = $item->rank + $direction;
				$item->save();
				break;
			}
		}
	}
	
	public function __call($method, $arguments) {
		// this is for editing of relationships
		
		if(isset($this->relationships[$method])) {
			$this->auto_render = FALSE;
			
			if(!isset($this->relationship_controllers[$method])) {
				// then we have to create a relationship from the relationship entry
				
				// you can specify additional columns to display by adding a columns => array() to your relationship config
				$columns = array('id' => array('restrict' => 'view'), $this->relationships[$method]['display_key'] => array());
				$columns += isset($this->relationships[$method]['columns']) ? $this->relationships[$method]['columns'] : array();
				$relationships = isset($this->relationships[$method]['relationships']) ? $this->relationships[$method]['relationships'] : array();
				
				$controller = new CMS_Core($method, inflector::singular($method), $columns, $relationships);
				
				// define auto_adjust_ranking in the relationship field to set the value for the relationship controller
				// otherwise the value will be inherited from the parent controller
				
				$controller->autoRedirect = TRUE;
				$controller->autoAdjustRanking = isset($this->relationships[$method]['auto_adjust_ranking']) ? $this->relationships[$method]['auto_adjust_ranking'] : $this->autoAdjustRanking;
				$controller->base_config['action_url'] = Kohana::config('admin.base').$this->controller_name.'/'.$method.'/';
				
				$methodName = array_shift($arguments);
				if(empty($methodName)) $methodName = 'index';
				
                call_user_func_array(array($controller, $methodName), $arguments);
			}
		} else {
			parent::__call($method, $arguments);
		}
	}
}

?>