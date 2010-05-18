<?
// required: $action_url, $title, $mode, $form
// optional: $info

if(empty($info)) $info = '';
?>
<a href="<?=$action_url?>view">&raquo; View <?=ucwords(inflector::plural($title))?></a>
<h2><?=$page_title?></h2>
<?=$info?>
<?=$form?>