<?
// $form, $submit_title
// optional: $form_title, 
?>
<?if(count($errors)):?>
<div class='error'>
<b>Submitted Information Has Errors:</b>
<ul style="margin-bottom:0;">
<?foreach($errors as $key => $value):?>
	<li><b>
<?
// although more messy than I would like this prevents two punctuation marks (i.e. ?: or .:) from appearing in the error box
$fieldDisplayName = strip_tags(isset($form[$key]->label) ? $form[$key]->label : inflector::titlize($key));

if(ctype_punct($fieldDisplayName[strlen($fieldDisplayName) - 1])) {
	echo substr($fieldDisplayName, 0, strlen($fieldDisplayName) - 1);
} else {
	echo $fieldDisplayName;
}
?>:</b> <?=$value?></li>
<?endforeach;?>
</ul>
</div>
<?endif;?>

<?=$form['open']?>
<fieldset>
<?=!empty($form_title) ? "<legend>".$form_title."</legend>" : ''?>
<?=$fields?>
<p style="text-align:center"><?=form::submit('submit_button', $submit_title)?></p>
</fieldset>
<?=$form['close']?>
