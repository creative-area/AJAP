<?php

class AjapStringHelper {
	
	public static function startsWith($string,$search) {
		return (strncmp($string, $search, strlen($search)) == 0);
	}

	public static function endsWith($string,$search) {
		return (strcmp(substr($string,-strlen($search)), $search) == 0);
	}
}

?>