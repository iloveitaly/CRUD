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

function create_path($targetPath) {
	$targetClimber = $targetPath;
	
	while(!file_exists($targetPath)) {			
		// if the 'climber' exists then reset to the top of the directory and drill down until we find a directory that doesn't exist
		if(file_exists($targetClimber)) {
			$targetClimber = $targetPath;
		} else if(file_exists(dirname($targetClimber))) {
			mkdir($targetClimber, 0775);
		} else {
			$targetClimber = dirname($targetClimber);
		}
	}
}

?>