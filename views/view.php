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
$relationButtons = '';

if(Kohana::config('admin.manage_relationships')) {
	foreach($relationships as $relationshipName) {
		$relationButtons .= "&nbsp;&nbsp; <a href=\"".$action_url.$relationshipName."\">&raquo; Manage ".inflector::titlize(inflector::plural($relationshipName))."</a>";
	}
}
?>
<h3><?=ucwords(inflector::plural($title))?></h3>
<?if($message = message::info()):?>
<div id="info_message"><p><?=$message ?></p></div>
<?endif;?>
<?=$info?>
<?=$addButton.$relationButtons?>
<?=$pages?>
<table>
<tr>
<?
foreach($columns as $columnName => $columnInfo):
	// if 'content' contains function then it is a custom content transformer
	
	if(is_callable($columnInfo['content'])) $sortString = '';
	else $sortString = '?'.url::get_query_string(array('s' => $columnName, 'd' => isset($get['s']) && $get['d'] == 'a' ? 'd' : 'a'));
	
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
		} else if(is_callable($columnInfo['content'])) {
			// if we have a custom content generator
			$columnDisplayData = $columnInfo['content']($entry);
		} else {
			switch($columnInfo['content']) {
				case 'date':
					$columnDisplayData = gmdate(Kohana::config('admin.date_format'), $entry->$columnName);
					break;
				case 'datetime':
					$columnDisplayData = gmdate(Kohana::config('admin.datetime_format'), $entry->$columnName);
					break;
				default:
					$columnDisplayData = $entry->$columnName;
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
<?=$addButton.$relationButtons?>