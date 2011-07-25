<header>
<?if(Kohana::config('admin.header')):?>
	<div id="header_info" class="wrapper">
		<a href="<?=Kohana::config("admin.base")?>login/logout" style="float:right; text-align:right;">Logout &rarr;</a>
		<a href="<?=url::base()?>" target="_blank">&larr; Visit Site</a>
	</div>
<?endif;?>
	<nav>
		<ul>
<?
foreach(Kohana::config('admin.sections') as $name => $path):
	if(is_array($path)):
?>
	<li>
		<a href=""><?=$name?></a>
		<ul>
<?foreach($path as $subName => $subPath):?>
			<li><a href="<?=Kohana::config('admin.base').$subPath?>"><?=$subName?></a></li>
<?endforeach;?>
		</ul>
	</li>
<?else:?>
	<li><a href="<?=Kohana::config('admin.base').$path?>/"<?=strpos(url::current(), $path) !== FALSE ? ' class="selected"' : ''?>><?=$name?></a></li>
<?endif;endforeach;?>
		</ul>
	</nav>
</header>
