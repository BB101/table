<?php
define("DB_NONE", 0);
define("DB_FANCY_HASHES", 1);
define("DB_ALL", 1);

class db {
  private static $num_rows = 0;
  private static $dbcon, $db, $debug_fp, $debug_on = true;
  public static $dbtime = 0;
  
  public static function num_rows() {
    return self::$num_rows;
  }
  
  public static function init($server, $username, $password, $db) {
    self::connect($server, $username, $password, $db);
  }

  private static function connect($server, $username, $password, $db) {
    self::$dbcon = mysql_connect($server, $username, $password, $db);
    self::$db = $db;
    mysql_select_db($db, self::$dbcon) or die("Error selecting $db");

    if (self::$debug_on) {
      self::$debug_fp = fopen("debug.log", "a");
      register_shutdown_function(array('db','endDebug'));
    }
  }
  
  public function usedb($db) {
      $start = microtime(true);
      mysql_select_db($db, self::$dbcon) or die("Error selecting $db");
      self::debug("use ".$db, microtime(true) - $start);
  }

  public static function get($SQL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    
    $row = @mysql_fetch_row($query);
    mysql_free_result($query);
    return (isset($row[0]) ? $row[0] : false);
  }

  public static function getRow($SQL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    
    $row = mysql_fetch_array($query, MYSQL_ASSOC);
    mysql_free_result($query);
    return $row;
  }

  public static function getAll($SQL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    
    $ret = array();
    while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
      $ret[] = $row;
    }
    mysql_free_result($query);
    return $ret;
  }

  public static function getCol($SQL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    
    $ret = array();
    while ($row = mysql_fetch_array($query)) {
      $ret[] = $row[0];
    }
    mysql_free_result($query);
    return $ret;
  }

  public static function get2Col($SQL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    
    $ret = array();
    while ($row = mysql_fetch_array($query)) {
      $ret[$row[0]] = $row[1];
    }
    mysql_free_result($query);
    return $ret;
  }

  public static function getAssoc($SQL, $gr = null, $flags = DB_ALL) {

    $start = microtime(true);
    $query = mysql_query($SQL, self::$dbcon);
    if (!$query) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);

      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::$num_rows = @mysql_num_rows($query);

    $ret = array();
    $first = true;
    while ($row = mysql_fetch_assoc($query)) {
      if ($first) {
        $first = false;
        if ($gr == null) $gr = array_keys($row);
        if (!is_array($gr) || count($gr) == 0) die(__CLASS__."::".__FUNCTION__." - unexpected grouping data, expecting array");
        $gr = self::checkAssocGroup($gr, array_keys($row));
      }
      reset($gr);
      self::getAssocArray($ret[$row[current($gr)]], $row, $gr, $flags);
    }

    mysql_free_result($query);
    self::debug($SQL, microtime(true) - $start);

    return $ret;
  }

  private static function checkAssocGroup($gr, $rowKeys) {
    $ngr = array();
    foreach ($gr as $label=>$field) {
      if (is_array($field)) {
        $gr[$label] = self::checkAssocGroup($field, $rowKeys);
        if (count($field) == 0) unset($gr[$label]);
      } elseif (preg_match('#\*#', $field)) {
        $qField = preg_quote($field);
        $pField = preg_replace('#\\\\\*#', '(.+?)', $qField);
        foreach ($rowKeys as $r) {
          if (preg_match('#^'.$pField.'$#i', $r, $m)) {
            $ngr[$m[1]] = $r;
          }
        }
        unset($gr[$label]);
      }
    }
    if ($ngr) {
      $gr = array_merge($ngr, $gr);
    }

    return $gr;
  }

  private static function getAssocArray(&$ret, $row, $gr, $flags) {
    foreach ($gr as $label=>$field) { //loop through each group
      if (is_int($label)) $label = $field; //sanitize numerical arrays from hash keys

      if (is_array($field)) { //if the field is an array, then take the first field from the next as row index

        if (!isset($ret[$label]))
          $ret[$label] = array();

        if ($row[current($field)] === null)
          continue;

        if ($flags & DB_FANCY_HASHES && count($field) == 1)
          $ret[$label][$row[current($field)]] = $row[current($field)];
        else if ($flags & DB_FANCY_HASHES && count($field) == 2)
          $ret[$label][$row[current($field)]] = $row[next($field)];

        else
          self::getAssocArray($ret[$label][$row[current($field)]], $row, $field, $flags);

      } else {
        $ret[$label] = $row[$field]; //otherwise it's a straight label=>row_field
      }
    }
  }

  public static function run($SQL) {

    $start = microtime(true);
    $q = mysql_query($SQL, self::$dbcon);
    if (!$q) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);
      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
    self::debug($SQL, microtime(true) - $start);
    self::$num_rows = @mysql_num_rows($query);
    return mysql_affected_rows();
  }
  
  public static function transaction($sql = false) {

    self::run("START TRANSACTION");
    self::run("BEGIN");
    if ($sql) {
      $trace = debug_backtrace();
      do {
        $caller = array_shift($trace);
      } while (isset($caller['file']) && $caller['file'] == __FILE__);
      trigger_error(__CLASS__."::".__FUNCTION__.' in '.$caller['file'].' on line '.$caller['line'].' - '.mysql_error()."<br/><pre>".htmlentities($SQL)."</pre>", E_USER_NOTICE);
      return false;
    }
  }

  public static function commit() {
    self::run("COMMIT");
  }

  public static function rollback() {
    self::run("ROLLBACK");
  }

  public static function insertedId() {
    return mysql_insert_id(self::$dbcon);
  }

  public static function escape($text) {
    return mysql_real_escape_string($text);
  }

  private static function debug($sql, $time) {
    self::$dbtime += $time;
    if (self::$debug_on) {
      $sql = preg_replace("#[\n\r]+#", " ",$sql);
      $sql = preg_replace("#\s+#", " ",$sql);
      fwrite(self::$debug_fp, round($time,4)." : ".trim($sql)."\n");
    }
  }

  public static function endDebug() {
    fwrite(self::$debug_fp, "---------------------------------------------\n");
    fclose(self::$debug_fp);
  }
}
?>