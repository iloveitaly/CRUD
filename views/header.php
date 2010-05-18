<div id="header">
<?if(Kohana::config('admin.header')):?>
	<div id="header_info" class="wrapper">
		<a href="<?=Kohana::config("admin.base")?>login/logout" style="float:right; text-align:right;">Logout &rarr;</a>
		<a href="<?=url::base()?>" target="_blank">&larr; Visit Site</a>
	</div>
<?endif;?>
	<ul class="tabs">
		
<?foreach(Kohana::config('admin.sections') as $name => $path):?>
		<li><a href="<?=Kohana::config('admin.base').$path?>/"<?=strpos(url::current(), $path) !== FALSE ? ' class="selected"' : ''?>><?=$name?></a></li>
<?endforeach;?>
	</ul>
</div>
