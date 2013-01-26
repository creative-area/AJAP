<?php

class AjapWriterException extends Exception {
	
	public function __construct($message,$code=null) {
		parent::__construct("AjapWriterException ($message)",$code);
	}
}

?>