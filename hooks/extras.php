<?

function array_add_value_suffix($array, $suffix) {
	foreach($array as $key => $value) {
		$array[$key] = $value.$suffix;
	}
	
	return $array;
}

function array_add_value_prefix($array, $suffix) {
	foreach($array as $key => $value) {
		$array[$key] = $suffix.$value;
	}
	
	return $array;	
}

function starts_with($check, $string) {
    if ($check === "" || $check === $string) {
        return true;
    } else {
        return (strpos($string, $check) === 0) ? true : false;
    }
}

function implode_with_keys($sep, $array, $selection) {
	$temp = array();
	
	foreach($selection as $key) {
		if(isset($array[$key]))
			$temp[] = $array[$key];
	}
	
	return implode($sep, $temp);
}

?>