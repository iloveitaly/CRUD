<?php defined('SYSPATH') OR die('No direct access allowed.');

$config['datetime_format'] = 'd-m-Y @ H:i';
$config['date_format'] = 'd-m-Y';

$config['header'] = TRUE;
$config['timezone_offset'] = 0;
$config['manage_relationships'] = true;

$config['editor_javascript'] = <<<EOL
var target = $$(".editor");

if(target.length > 0) {
	target = target[0];

	// remove the label
	$$("label[for=\'" + target.getProperty("id") + "\']")[0].destroy();

	CKEDITOR.replace(target, {
		height:"500px",
		width:"960px",
		customConfig: '/includes/js/core/ckeditor/config.js'
	});
}

EOL;

$config['datepicker_javascript'] = <<<EOL
new DatePicker(".%s", {
	format: "%s",
	pickerClass: "datepicker_dashboard_old",
	allowEmpty: true
});

EOL;

$config['datetimepicker_javascript'] = <<<EOL
new DatePicker('.%s', {
	pickerClass:'datepicker_dashboard_old',
	format:'%s',
	timePicker: true,
	allowEmpty: true
});

EOL;
?>