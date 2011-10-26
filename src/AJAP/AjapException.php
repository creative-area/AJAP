<?php

class AjapException extends Exception {
  
  private $errorCodes;

  public function __construct($errorCodes) {
    
    if (is_array($errorCodes)) $this->errorCodes = array_values($errorCodes);
    else $this->errorCodes = array($errorCodes);
  }
  
  public function getErrorCodes() {
    return $this->errorCodes;
  }
}

?>