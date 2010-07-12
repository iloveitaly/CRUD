<?
// $form, $submit_title
// optional: $form_title, 
?>
<?if(count($errors)):?>
<b>Submitted Information Has Errors:</b>
<ul>
<?foreach($errors as $key => $value):?>
	<li><b><?=isset($form[$key]->label) ? $form[$key]->label : inflector::titlize($key)?>:</b> <?=$value?></li>
<?endforeach;?>
</ul>
<?endif;?>

<?=$form['open']?>
<fieldset>
<?=isset($form_title) ? "<legend>".$form_title."</legend>" : ''?>
<?=$fields?>
<p style="text-align:center"><?=form::submit('submit_button', $submit_title)?></p>
</fieldset>
<?=$form['close']?>
