<?
class inflector extends inflector_Core {
	public static function titlize($rawTitle) {
		if(strlen($rawTitle) < 3) {
			return strtoupper($rawTitle);
		} else {
			return ucwords(self::humanize($rawTitle));
		}
	}
}
?>