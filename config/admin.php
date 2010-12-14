<?php defined('SYSPATH') OR die('No direct access allowed.');

$config['datetime_format'] = 'd-m-Y @ H:i';
$config['date_format'] = 'd-m-Y';

$config['header'] = TRUE;
$config['timezone_offset'] = 0;

$config['base'] = '/';
$config['manage_relationships'] = true;

$config['editor_javascript'] = <<<EOL
var target = $$(".editor");

if(target.length > 0) {
	target = target[0];

	// remove the label
	$$("label[for=\'" + target.getProperty("id") + "\']")[0].destroy();

	// CKEDITOR.plugins.add("CKEDITOR.plugins.add");
	CKEDITOR.replace(target, {
		height:"500px"
	});
}

EOL;
?>