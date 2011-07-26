<?if($message = message::info()): ?>
<div id="error_message"><p><?=$message ?></p></div>
<?endif; ?>
<form action="<?=join_paths(Kohana::config("admin.base"), 'login/submit')?>" method="post" accept-charset="utf-8" id="login" class="hform">
	<p><label for="username">Username</label><input type="text" name="username" value="" id="username" /></p>
	<p><label for="password">Password</label><input type="password" name="password" value="" id="password" /></p>

	<p><input type="submit" value="Login &rarr;" /></p>
</form>