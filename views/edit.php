<?
// required: $action_url, $title, $mode, $form
// optional: $info

if(empty($info)) $info = '';
?>
<p class="buttons wrapper" style="padding-top:20px"><a href="<?=$action_url?>view">&raquo; Back to <?=ucwords(inflector::plural($title))?></a></p>
<h2><?=$page_title?></h2>
<?=$info?>
<?=$form?>