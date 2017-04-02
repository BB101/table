<?php
abstract class field {
  private $name, $params, $value;
  
  function __construct($data) {
    $this->name = $data['name'];
    $this->params = $data['params'];
    $this->value = $data['value'];
  }
  
  abstract public function edit();
  
  public function value($new = null) {
    if (!is_null($new)) $this->value = $new;
    return $this->value;
  }
  
  public function value_null() {
    $this->value = null;
  }
}
?>