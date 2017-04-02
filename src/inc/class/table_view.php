<?php
//represents a filtered, ordered view of a table
class table_view implements Iterator, Countable {
  public $table; //reference to table object
  public $search, $order = array(), $unique, $data;
  
  function __construct(&$table, $search, $order = null) {
    $this->table =& $table;
    $this->search = $search;
    $this->order = $order;
  }
  
  function __toString() {
    $this->load_keys();
    
    if ($this->unique && method_exists($this->first(), "__toString")) {
      return $this->first()->__toString();
    } else {
      return $this->table."->get(".$this->search.", ".$this->order.")";
    }
  }
  
  //loader functions
  private $keys = null; //list of keys (list of key/value pairs)
  private function load_keys() {
    if (!is_null($this->keys)) return;
    
    $enc_key = json_encode(array("search" => $this->search, "order" => $this->order));

    if (!isset($this->table->get_cache[$enc_key])) {
      $this->table->get_cache[$enc_key] = da::get_keys($this->table, $this->search, $this->order);
    }

    $this->keys = $this->table->get_cache[$enc_key]['keys'];
    $this->unique = $this->table->get_cache[$enc_key]['unique'];
  }

  private $rows = array();
  private function load_rows($offset, $limit = 10) {
    global $preview;

    $this->load_keys();
    
    $to_load = array(); $positions = array();
    $keys = array_slice($this->keys, $offset, $limit);
    foreach ($keys as $key) {
      if (!isset($this->data[$offset])) {
        ksort($key);
        $enc_key = json_encode(array("key" => $key));
        if (isset($this->table->get_cache[$enc_key])) {
          $this->rows[$offset] = $this->table->get_cache[$enc_key];
        } else {
          $to_load[] = $key;
          $positions[$enc_key] = $offset;
        }
      }
      $offset++;
    }
    
    if ($to_load) foreach ($to_load as $idx => $search) {
      if (isset($preview['data'])) foreach ($preview['data'] as $data) {
        if ($data['table'] == $this->table->table) {
          $ks = array();
          foreach ($this->table->keys as $key) {
            $ks[$key] = $data['row'][$this->table->table.".".$key];
          }
          ksort($ks);
          $enc_key = json_encode(array("key" => $ks));
          $search_key = json_encode(array("key" => $search));
          
          if ($search_key == $enc_key) {
            $class = $this->table->row_class;
            $row = new $class($this->table, $data['row']);
            $this->rows[$positions[$enc_key]] = $row;
            $this->table->get_cache[$enc_key] = $row;
            unset($row, $to_load[$idx]);
          }
        }
      }
    }
    
    if ($to_load) {
      $rs = da::get_rows($this->table, $to_load);
      //$class = $this->table->row_class;
      foreach ($rs as $r) {
        $class = table($r['table_type'])->row_class;
        $ks = array();
        foreach ($this->table->keys as $key) {
          $ks[$key] = $r[$this->table->table.".".$key];
        }
        ksort($ks);
        $enc_key = json_encode(array("key" => $ks));
        
        $row = new $class(table($r['table_type']), $r);
        $this->rows[$positions[$enc_key]] = $row;
        $this->table->get_cache[$enc_key] = $row;
        unset($row, $r);
      }
    }
  }

  //countable function
  function count() {
    $this->load_keys();
    return count($this->keys);
  }
  
  //iterator functions
  private $position = 0;
  function rewind() {
    $this->position = 0;
  }
  function key() {
    return $this->position;
  }
  function current() {
    $this->load_keys();
    if (!$this->valid()) return false;

    if (!isset($this->rows[$this->position])) {
      $this->load_rows($this->position);
    }
    return $this->rows[$this->position];
  }
  function valid() {
    $this->load_keys();
    
    return isset($this->keys[$this->position]);
  }
  function next() {
    $this->position++;
  }
  //end iterator
  
  //custom functions
  public function first() {
    $this->load_keys();

    $this->rewind();
    return $this->current();
  }
  
  public function last() {
    $this->load_keys();

    $this->position = count($this->keys)-1;
    return $this->current();
  }
  
  public function reverse() {
    
  }
  
  //returns another field_view based on this view
  public function slice($offset, $limit = null) {
    $ret = clone($this);
    $ret->load_keys();
    $ret->keys = array_slice($ret->keys, $offset, $limit);
    return $ret;
  }
  
  public function random($count = 1, $skip = array()) {
    $this->load_keys();
    
    if (count($this) == 0) return false;
    
    $singular = false;
    if ($count == 1) $singular = true;
    
    $ret = array();
    $positions = range(0, count($this)-1);
    shuffle($positions);
    while (count($ret) < $count && $positions) {
      $pos = array_pop($positions);
      $this->load_rows($pos, 1);
      $found = false;
      foreach ($skip as $i) {
        if ($i->keys == $this->rows[$pos]->keys) {
          $found = true;
          break;
        }
      }
      if (!$found) {
        $ret[] = $this->rows[$pos];
      }
    }
    
    if ($singular) return current($ret);
    return $ret;
  }
  
  //add the following to the current table_view, return new table_view
  public function get($search = null, $order = null) {
    //add to current filter, change current ordering
    return new self($this->table, $this->search.($search ? " AND (".$search.")" : ""), $order ? $order : $this->order);
  }
  
  //these functions make table_view act as a table_row when the search matched a unique index
  function __get($k) {
    $this->load_keys();
    
    if ($this->unique) {
      if ($this->first()) {
        return $this->first()->{$k};
      }
    }
    die("tried to get '".$k."' from ".$this."<br/>".print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),1));
  }
  function __call($k, $args) {
    $this->load_keys();
    
    if ($this->unique) {
      return call_user_func_array(array($this->first(), $k), $args);
    }
    die("tried to call '".$k."' from ".$this."<br/>".print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),  1));
  }
}
?>