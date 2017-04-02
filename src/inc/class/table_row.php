<?php
class table_row {
  public $table; //reference to table object
  public $keys, $data;
  
  function __construct($table, $data) {
    $this->table = $table;
    foreach ($data as $name => $val) {
      if ($name == "table_type") continue;

      list($t,$f) = explode(".", $name, 2);
      $ref = $table;
      
      while ($ref->table != $t) {
        if (!$ref->parent || !$ref->parent['table']) continue 2;
        $ref = $ref->parent['table'];
      }
      $data = $ref->fields[$f];
      $type = $data['type'];
      $data['value'] = $val;
      $this->data[$ref->table.".".$f] = new $type($data);
    }
    
    foreach ($this->table->keys as $key) {
      $this->keys[$key] = $this->data[$this->table->table.".".$key]->value();
    }
  }
  
  function __toString() {
    return "(Object ".get_class($this).") &lt;= ".$this->table."->get(".json_encode($this->keys).")";
  }

  //get catcher for undefined
  //will look for fields, then:
  //will look for relationships, then:
  //will call $this->_get_$k() and cache the return
  private static $get_data = array();
  function __get($k) {
    //first see if it's an exact match
    if (isset($this->data[$k])) {
      return $this->data[$k]->value();
    }

    //look through our table fields to see if it's in there
    //bubbling up through our parents
    $ref = $this->table;
    do {
      //find data
      if (isset($this->data[$ref->table.".".$k])) {
        return $this->data[$ref->table.".".$k]->value();
      }
      //find a link
      if (isset($ref->link[$k])) {
        $link = $ref->link[$k];
        
        $dest_table = $link['table'];
        $order = $link['order'];
        
        $binding = "";
        foreach ($link['binding'] as $local => $dest) {
          $binding .= $dest_table->table.".".$dest." = '".db::escape($this->data[$ref->table.".".$local]->value())."' AND ";
        }
        
        if ($link['filter']) {
          $binding .= $link['filter']." AND ";
        }
        $binding = substr($binding,0,-5);
        
        //drill back to the root item and initiate that
        while ($dest_table->parent && $dest_table->parent['table']) {
          $dest_table = $dest_table->parent['table'];
        }
        
        return $dest_table->get($binding, $order);
      }
      $ref =& $ref->parent['table'];
    } while ($ref);
        
    $m = '_get_'.$k; //use this method name to overload that var
    if (method_exists($this, $m)) {
      if (!isset(self::$get_data[get_class($this)][$this->id][$k])) {
        self::$get_data[get_class($this)][$this->id][$k] = $this->$m(); //save the data so we don't have to call again
      }
      return self::$get_data[get_class($this)][$this->id][$k];
    }
    $trace = debug_backtrace(); //error
    trigger_error('Undefined property via __get(): '.get_class($this).'::'.$k.' in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_NOTICE);
    return null;
    //to do _get_$k()
  }
  
  public function edit() {
    //
  }
 
  public function save($fields) {
    $this->table->save(array_merge($this->keys, $fields));
    $ref =& $this->table;
    do {
      foreach ($fields as $k => $v) {
        $k = explode(".", $k, 2);
        if (count($k) == 1) {
          if (isset($ref->fields[$k[0]])) {
            if (is_null($v)) {
              $this->data[$ref->table.".".$k[0]]->value_null();
            } else {
              $this->data[$ref->table.".".$k[0]]->value($v);
            }
          }          
        } else if ($k[0] == $ref->table && isset($ref->fields[$k[1]])) {
          if (is_null($v)) {
            $this->data[$ref->table.".".$k[0]]->value_null();
          } else {
            $this->data[$ref->table.".".$k[1]]->value($v);
          }
        }
      }
      $ref =& $ref->parent['table'];
    } while ($ref);
  }
  
  public function delete() {
    $sql = "";
    foreach ($this->keys as $k=>$v) {
      $sql .= "`".$k."` = '".db::escape($v)."' AND ";
    }
    $sql = substr($sql, 0, -5);
    $this->table->delete($sql);
  }
  
  public function has_field($field) {
    return isset($this->data[$this->table->table.".".$field]);
  }
  
  public function has_link($link) {
    return isset($this->table->link[$link]);
  }
}
?>