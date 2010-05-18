<?
class url extends url_Core {
	public static function get_query_string(Array $queryVars, $keepOld = false) {
		$current = input::instance()->get();
		
		if($keepOld) $new = array_merge($current, $queryVars);
		else $new = $queryVars;

		return http_build_query($new);
	}
}

?>