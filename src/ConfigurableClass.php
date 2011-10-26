<?php

abstract class ConfigurableClass {

  private $options;
  
  protected function forceOptions($options) {
    
    $this->options = $options;
  }

  public abstract function resetOptions();

  public function setOptions($options) {
    if (is_array($options)) foreach ($options as $k => $v) $this->options[$k] = $v;
  }

  public function setOption($k,$v) {
    $this->options[$k] = $v;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getOption($k) {
    return isset($this->options[$k])?$this->options[$k]:null;
  }

  public function __construct($options=null) {

    $this->resetOptions();
    $this->setOptions($options);
  }
}

?>