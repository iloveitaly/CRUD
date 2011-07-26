<?
// required: $options
// optional: $info

// normalize the sorting information a bit
$get = input::instance()->get();
if(!isset($get['d'])) $get['d'] = 'a';

if(empty($info)) $info = '';

// for convience
$timezoneOffset = Kohana::config('admin.timezone_offset');

$addButton = array_search('add', $options) !== FALSE ? "<a href=\"{$action_url}edit/\">&raquo; Add New {$title}</a>" : '';
$csvButton = array_search('csv', $options) !== FALSE ? "<a href=\"{$action_url}csv\">&raquo; Download CSV File</a>" : '';
$relationButtons = '';

if(Kohana::config('admin.manage_relationships')) {
	foreach($relationships as $relationshipName => $relationshipInfo) {
		if($relationshipInfo['manage']) {
			$relationButtons .= "&nbsp;&nbsp; <a href=\"".$action_url.$relationshipName."\">&raquo; Manage ".inflector::titlize($relationshipInfo['type'] == 'one' ? inflector::plural($relationshipName) : $relationshipName)."</a>";
		}
	}
}
?>
<h1><?=ucwords(inflector::plural($title))?></h1>
<?if($message = message::info()):?>
<div id="info_message"><p><?=$message ?></p></div>
<?endif;?>
<?=$info?>
<?foreach($quick_search as $searchField => $searchInfo):?>
<p><b>Quick Search by <?=inflector::titlize($searchField)?>:</b> <input type="text" name="<?=$searchField?>_search" class="quick_search auto-clear" title="Type to search..." id="<?=$searchField?>_search" /></p>
<?endforeach?>
<p class="buttons wrapper">
<?=$addButton.$relationButtons?>
</p>
<?=$pages?>
<table>
<tr>
<?
$relationshipViewFieldSuffix = strlen($this->crud->relationshipViewFieldSuffix);

foreach($columns as $columnName => $columnInfo):
	// if 'content' contains function then it is a custom content transformer
	
	// all relationships are handled as content functions, but they are also defined in the relationship array
	// one-to-one relationships are sortable so we have to check for them and handle them as a special case
	// note that all relationship fields are view-only fields thus they will have the $relationshipViewFieldSuffix attatched to them
	
	if(strlen($columnName) > $relationshipViewFieldSuffix)
		$possibleRelationshipFieldName = substr($columnName, 0, -$relationshipViewFieldSuffix);

	if(!empty($possibleRelationshipFieldName) && isset($relationships[$possibleRelationshipFieldName]) && $relationships[$possibleRelationshipFieldName]['type'] == 'one') {
		$sortString = '?'.url::get_query_string(array('s' => $possibleRelationshipFieldName.'_id', 'd' => isset($get['s']) && $get['d'] == 'a' ? 'd' : 'a'));
	} else if(is_callable($columnInfo['content']) && $columnInfo['type'] == 'custom') {
		$sortString = '';
	} else {
		$sortString = '?'.url::get_query_string(array('s' => $columnName, 'd' => isset($get['s']) && $get['d'] == 'a' ? 'd' : 'a'));
	}
	
	if(isset($columnInfo['label'])) $headerTitle = $columnInfo['label'];
	else $headerTitle = inflector::titlize($columnName);
?>
	<th><a href="<?=$action_url.$sortString?>"><?=$headerTitle?><?=isset($get['s']) && $get['s'] == $columnName ? ($get['d'] == 'a' ? ' &uarr;' : ' &darr;') : ''?></a></th>
<?endforeach;?>
	<?=array_search('edit', $options) !== FALSE ? '<th>Edit</th>' : ''?>
	<?=array_search('delete', $options) !== FALSE ? '<th>Delete</th>' : ''?>
</tr>
<?foreach($entries as $entry):?>
<tr>
	<?
	foreach($columns as $columnName => $columnInfo):
		if($columnInfo['type'] == 'select') {
			// try to map relationships to their actual name
			$columnDisplayData = array_search($entry->$columnName, $columnInfo['values']);
			if($columnDisplayData === FALSE) $columnDisplayData = "Node Error (".$entry->$columnName.")";
		} else if(is_callable($columnInfo['content']) && !in_array($columnInfo['content'], array('date', 'datetime'))) {
			// if we have a custom content generator
			$columnDisplayData = $columnInfo['content']($entry);
		} else {
			switch($columnInfo['content']) {
				case 'date':
					// force nowrap for the dates... it is ackward to have a wrapping date
					$columnDisplayData = "<span style='white-space:nowrap'>".gmdate(Kohana::config('admin.date_format'), $entry->$columnName)."</span>";
					break;
				case 'datetime':
					$columnDisplayData = gmdate(Kohana::config('admin.datetime_format'), $entry->$columnName);
					break;
				default:
					// soft hypens: http://stackoverflow.com/questions/320184/who-has-solved-the-long-word-breaks-my-div-problem-hint-not-stackoverflow
					$columnDisplayData = preg_replace('/([^\s-]{5})([^\s-]{5})/', '$1&shy;$2', $entry->$columnName);
			}
		}
	?>
		<td><?=$columnDisplayData?></td>
	<?endforeach;?>
		<?=array_search('edit', $options) !== FALSE ? "<td><input type=\"button\" value=\"Edit\" onclick=\"window.location = '{$action_url}edit/{$entry->id}';\" /></td>" : ''?>
		<?=array_search('delete', $options) !== FALSE ? "<td><input type=\"button\" value=\"Delete\" onclick=\"if(confirm('Sure about that?')) {window.location = '{$action_url}delete/{$entry->id}';}\" /></td>" : '' ?>
</tr>
<?endforeach;?>
</table>
<?=$pages?>
<div class="buttons wrapper">
<?=$addButton.$relationButtons.$csvButton?>
</div>