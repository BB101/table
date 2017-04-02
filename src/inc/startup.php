<?php
//load config, $cfg and $_ populated
require_once(dirname(__FILE__)."/config.php");

//autoloader for classes, looks like /inc/class/
if (!function_exists("__autoload")) {
  function __autoload($class) {
    if (file_exists(dirname(__FILE__)."/class/".$class.".php")) {
      require_once(dirname(__FILE__)."/class/".$class.".php");
    } else if (file_exists(dirname(__FILE__)."/class/field/".$class.".php")) {
      require_once(dirname(__FILE__)."/class/field/".$class.".php");
    } else {
      die("Can't find that class you wanted '".$class."'");
    }
  }
}

//evil gpc cocks up a lot of things, if i want to use it in SQL, ill escape it myself!
if (get_magic_quotes_gpc()) {
  $_GET = stripSlash($_GET);
  $_POST = stripSlash($_POST);
  $_COOKIE = stripSlash($_COOKIE);
  $_REQUEST = stripSlash($_REQUEST);
}

function stripSlash($v) {
  if (is_array($v)) {
    $v = array_map("stripSlash",$v);
  } else {
    $v = stripslashes($v);
  }
  return $v;
}

//start a session if it's not already
if (!session_id()) {
  session_start();
}

//connect to db
db::init($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_name']);

//we store our table objects here
function table($table, &$object = false) {
  static $tables = array();
  if ($object) {
    $tables[$table] =& $object;
  } else {
    if (isset($tables[$table])) {
      return $tables[$table];
    } else {
      die("Couldn't find table ".$table);
    }
  }
}
?>