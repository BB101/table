<?php
//data access abstraction layer
//replace this layer if you want to interface to a different datasource

//3rd party sql parser
require(dirname(__FILE__)."/../3rdParty/php-sql-parser.php");

abstract class da {
  //fetch some rows from a table
  public static function get_rows($table, $keys) {
    foreach ($keys as $k=>$v) {
      if (!is_array($v)) $keys = array($keys);
      break;
    }
    
    $where = "";
    foreach ($keys as $r) {
      $where .= "(";
      foreach ($r as $k=>$v) {
        $where .= "`".$table->table."`.`".$k."` = '".$v."' AND ";
      }
      $where = substr($where, 0, -5).") OR ";
    }
    if ($where) $where = substr($where, 0, -4);
    $q = self::get_select_tree($table, $keys);
    return db::getAll("SELECT ".$q['select'].$q['from'].($where ? " WHERE ".$where : ""));
  }

  //fetch list of ids for a particular search, this ones complicated!
  //will take string "field = 'value'", "field <> 'value'", also: < > <= >= LIKE 'value'
  //will take multiple fields: "field = 'value' OR field2 = 'value2'", AND and IN
  //will query other tables based on closest relationship: "table.field = 'value'"
  //will take nested logic brackets: "(field = 'value' AND field2 = value2) OR field3 = value3"
  //will match values from other tables: "field = table.value"
  static public function get_keys($table, $search, $order = "") {
    $sql = self::recreate_sql($table, $search, $order);
    $q = self::get_select_tree($table, $search);
    return array(
      "keys" => db::getAll("SELECT DISTINCT ".$q['keys'].$q['from']." ".$sql['from'].($sql['where'] ? " WHERE ".$sql['where'] : "")." GROUP BY ".$q['keys'].($sql['order_by'] ? " ORDER BY ".$sql['order_by'] : "")),
      "unique" => $sql['unique'],
    );
  }
  
  private static function recreate_sql($table, $search, $order) {
    $from = ""; $where = "";  $order_by = ""; $local_where_fields = array();
    $joins = array(); $join_id = 0;
    //echo "<hr/>";
    $recurse_bracket = function($tokens, $mode = "where", $seperator = "") use (&$recurse_bracket, &$table, &$from, &$where, &$order_by, &$joins, &$join_id, &$local_where_fields) {
      foreach ($tokens as $token) {
        if ($token['expr_type'] == 'colref') {
          $token['base_expr'] = preg_replace("#^`|`(?=\.)|(?<=\.)`|`$#","", $token['base_expr']); //strip the backtick quotes
          //echo "resolving ".$token['base_expr']."<br />";

          $parts = explode(".", $token['base_expr']);
          $field = array_pop($parts);
          if ($parts && $parts[0] == $table->table) array_shift($parts);

          $cur_table =& $table;
          $direct_parent = true;
          $parent = $cur_table->table;
          $part_stack = array();
          foreach ($parts as $part) {
            if ($cur_table->parent && $cur_table->parent->table == $part) {
              //echo "found parent: ".$part."<br>";
              if (!$direct_parent) die("yeah, i gotta get round to writing that");
              $cur_table =& $cur_table->parent;
            } else if (isset($cur_table->children[$part])) {
              //echo "found child: ".$part."<br>";
              
            } else if (isset($cur_table->link[$part])) {
              //echo "found link: ".$part."<br>";
              $direct_parent = false;

              $from .= "LEFT JOIN `".$cur_table->link[$part]['table']->table."` ".$part." ON ";
              foreach ($cur_table->link[$part]['binding'] as $foreign => $local) {
                $from .= $parent.".`".$foreign."` = ".$part.".`".$local."` AND ";
              }
              $from = substr($from, 0, -4);
              $parent = $part;
              
              $cur_table =& $cur_table->link[$part]['table'];
              $local_where_fields[] = $foreign;
            } else die("Failed to resolve ".$token['base_expr']." got to ".$part." and lost it");
          }
          
          if (!isset($cur_table->fields[$field])) die("Failed to resolve ".$token['base_expr']." got to ".$field." and lost it");
          if ($mode == "where") {
            if (isset($part)) {
              $where .= "`".$part."`.`".$field."`";
            } else {
              $where .= "`".$table->table."`.`".$field."`";
              $local_where_fields[] = $field;
            }
          } else {
            $order_by .= "`".$parent."`.`".$field."`";
          }

        } else if ($token['expr_type'] == "operator") {
          if ($mode == "where") $where .= " ".$token['base_expr']." ";
          else $order_by .= " ".$token['base_expr']." ";

        } else if ($token['expr_type'] == "const") {
          if ($mode == "where") $where .= $token['base_expr'];
          else $order_by .= $token['base_expr'];

        } else if ($token['expr_type'] == "in-list") {
          if ($mode == "where") {
            $where .= " (";
            $recurse_bracket($token['sub_tree'], "where", ",");
            $where = substr($where, 0, -1);
            $where .= ") ";
          } else {
            $order_by .= " (";
            $recurse_bracket($token['sub_tree'], "order");
            $order_by .= ") ";
          }

        } else if ($token['expr_type'] == "bracket_expression") {
          if ($mode == "where") {
            $where .= " (";
            $recurse_bracket($token['sub_tree'], "where");
            $where .= ") ";
          } else {
            $order_by .= " (";
            $recurse_bracket($token['sub_tree'], "order");
            $order_by .= ") ";
          }

        } else if ($token['expr_type'] == "aggregate_function") {
          if ($mode == "where") die("can't use aggregate functions yet, please do this manually<br/>".var_dump($token));
          else {
            $order_by .= " ".$token['base_expr']."(";
            $recurse_bracket($token['sub_tree'], "order");
            $order_by = substr($order_by,0,-2).")";
          }
        } else if ($token['expr_type'] == "reserved") {
          if ($mode == "where") $where .= $token['base_expr']." ";
          else $order_by .= $token['base_expr']." ";

        } else if ($token['expr_type'] == "function") {
          if ($mode == "where") {
            $where .= $token['base_expr']."(";
            $recurse_bracket($token['sub_tree'], "where", ",");
            $where .= ")";
          } else {
            $order_by .= $token['base_expr']."(";
            $recurse_bracket($token['sub_tree'], "order", ",");
            $order_by = substr($order_by, 0, -2).")";
          }
        } else {
          die("unknown expression type (".$token['expr_type'].")<br/>".var_dump($token));
        }
        if ($mode == "where") {
          $where .= $seperator;
        } else {
          if (isset($token['direction'])) {
            $order_by .= " ".$token['direction'];
          }
          $order_by .= ", ";
        }
      }      
    };
    $parser = new PHPSQLParser("WHERE ".$search.($order ? " ORDER BY ".$order : ""));
    $recurse_bracket($parser->parsed['WHERE'], "where");
    if (isset($parser->parsed['ORDER'])) {
      $recurse_bracket($parser->parsed['ORDER'], "order");
      $order_by = substr($order_by, 0, -2);
    }
        
    $unique = false;
    foreach ($table->unique as $fields) {
      foreach ($fields as $f) {
        if (!in_array($f, $local_where_fields)) {
          $unique = false;
          continue 2;
        }
        $unique = true;
      }
      if ($unique) break;
    }
    
    return array(
      "from" => $from,
      "where" => $where,
      "order_by" => $order_by,
      "unique" => $unique,
    );
  }

  //build a mighty sql query!
  private static function get_select_tree($start, $search) {
    $ref = $start;
    
    $recurse = function($ref, $first = false) use (&$recurse, $start) {
      //gathering this info
      if (!$first) $table = $ref['table'];
      else $table = $ref;
      
      $ret = array("select" => "", "keys" => "", "case" => "", "from" => "");
      
      //recurse down tree
      foreach ($table->children as $child) {
        $ret2 = $recurse($child, false);
        $ret['select'] = $ret2['select'].$ret['select'];
        $ret['keys'] = $ret2['keys'].$ret['keys'];
        $ret['case'] = $ret2['case'].$ret['case'];
        $ret['from'] = $ret2['from'].$ret['from'];
      }
      
      //build a CASE statement to work out what instance of table_row to initiate
      $ret['case'] .= " WHEN";
      foreach ($table->keys as $key) {
        $ret['case'] .= " `".$table->table."`.`".$key."` IS NOT NULL AND ";
        $ret['keys'] .= "`".$table->table."`.`".$key."`, ";
      }
      $ret['case'] = substr($ret['case'],0,-5)." THEN '".$table->table."'";
      
      //build the normal select fields, mapped to {table}.{field}
      foreach ($table->fields as $field => $data) {
        $ret['select'] .= "`".$table->table."`.`".$field."` as `".$table->table.".".$field."`, ";
      }
      
      if ($first) {
        $ret['from'] = " FROM `".$table->table."`".$ret['from'];
        $ret['select'] .= " CASE".$ret['case']." END as table_type";
        $ret['keys'] = substr($ret['keys'],0,-2);
        unset($ret['case']);
      } else {
        $ret['from'] .= " LEFT JOIN `".$table->table."` ON ";
        foreach ($ref['binding'] as $local => $foreign) {
          $ret['from'] .= "`".$table->table."`.`".$local."` = `".$start->table."`.`".$foreign."` AND ";
        }
        $ret['from'] = substr($ret['from'], 0, -5);
      }
      return $ret;
    };
    return $recurse($ref, true);
  }
  
  //will output definition for well formed mysql databases
  public static function generate_define() {
    if (file_exists("inc/define.php")) {
      require("inc/define.php");
    }

    //load current schema  
    $schema = array();
    $rels = array();
    
    foreach (db::getCol("SHOW TABLES") as $table) {
      $cur_t = table($table);
      $create_table = db::getRow("SHOW CREATE TABLE `".$table."`");
      $schema[$table] = array(
        "table" => $table,
        "row_class" => $cur_t && $cur_t->row_class != "table_row" ? $cur_t->row_class : '',
        "view_class" => $cur_t && $cur_t->view_class != "table_view" ? $cur_t->view_class : '',
        "keys" => array(),
        "unique" => array(),
        "fields" => array()
      );
      
      preg_match('#^\s*CREATE\s+TABLE\s+`[^`]+`\s+\(\s*(.+)\s+\) ENGINE#is', $create_table['Create Table'], $m);
      $rs = preg_split("#,\n#", $m[1]);

      foreach ($rs as $r) {
        $r = trim($r);
        
        if (preg_match('#^`([^`]+)` (.+)$#', $r, $m)) {
          /*
          if ($cur_t && isset($cur_t->fields[$m[1]])) {
            $fp = $cur_t->fields[$m[1]]; //preserve field type
          } else {
          */
            $fp = self::get_type($m[2]);
          //}
          
          $schema[$table]['fields'][$m[1]] = array(
            'name' => $m[1],
            'type' => $fp['type'],
            'params' => $fp['params'],
          );

        } else if (preg_match('#^PRIMARY KEY \(`([^\)]+)`\)#', $r, $m)) {
          $schema[$table]['keys'] = explode("`,`", $m[1]);
          $schema[$table]['unique']['primary'] = $schema[$table]['keys'];
          foreach ($schema[$table]['keys'] as $key) {
            $schema[$table]['fields'][$key]['type'] = "primary";
          }
        } else if (preg_match('#^UNIQUE KEY `([^`]+)` \(`([^\)]+)`\)#', $r, $m)) {
          $schema[$table]['unique'][$m[1]] = explode("`,`", $m[2]);
        }
      }
    }

    //now we have schemas, make relationship links
    foreach (db::getCol("SHOW TABLES") as $table) {
      $cur_t = table($table);
      $create_table = db::getRow("SHOW CREATE TABLE `".$table."`");
      preg_match('#^\s*CREATE\s+TABLE\s+`[^`]+`\s+\(\s*(.+)\s+\) ENGINE#is', $create_table['Create Table'], $m);
      $rs = preg_split("#,\n#", $m[1]);

      foreach ($rs as $r) {
        $r = trim($r);
        if (preg_match('#^CONSTRAINT `[^`]+` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)#', $r, $m)) {
          list(,$field,$dest_table,$dest_field) = $m;
          
          $sharedPrimary =
            in_array($field, $schema[$table]['keys']) &&
            in_array($dest_field, $schema[$dest_table]['keys']) &&
            count($schema[$dest_table]['keys']) == 1 &&
            count($schema[$table]['keys']) == 1;
          
          $cur_l = false;
          if ($cur_t) foreach ($cur_t->link as $l) {
            if ($l['table']->table == $dest_table && $l['binding'] == array($m[1] => $m[3])) {
              $cur_l = $l;
              break;
            }
          }
          
          $new_rel = array();
          $new_rel = array_merge($new_rel, array(
            'table' => $dest_table,
            'name' => ($cur_l ? $cur_l['name'] : $field),
            'type' => ($cur_l ? $cur_l['type'] : ($sharedPrimary ? "parent" : "one")),
            'binding' => array($m[1] => $m[3]),
            'order' => ($cur_l ? $cur_l['order'] : ""),
            'filter' => ($cur_l ? $cur_l['filter'] : ""),
          ));
          
          $rels[$table][] = $new_rel;
          
          $cur_l = false;
          if ($cur_t && table($dest_table)) foreach (table($dest_table)->link as $l) {
            if ($l['table']->table == $table && $l['binding'] == array($dest_field => $field)) {
              $cur_l = $l;
              break;
            }
          }

          $new_rel = array();
          $new_rel = array_merge($new_rel, array(
            'table' => $table,
            'name' => ($cur_l ? $cur_l['name'] : $table),
            'type' => ($cur_l ? $cur_l['type'] : ($sharedPrimary ? "child" : 'many')),
            'binding' => array($dest_field => $field),
            'order' => ($cur_l ? $cur_l['order'] : ""),
            'filter' => ($cur_l ? $cur_l['filter'] : ""),
          ));
          
          $rels[$dest_table][] = $new_rel;
          
          if (!$sharedPrimary && (!$cur_t || !isset($cur_t->fields[$field]))) {
            $schema[$table]['fields'][$field]['type'] = "select";
          }
        }
      }
    }
    
    //now we have a map of information about this database, output it
    echo "//autogenerated definition for tables\n\n";
    foreach ($schema as $table => $data) {
      echo 'table("'.$table.'", new table('.var_export($data, true).'));'."\n\n";
    }
    
    foreach ($rels as $table => $links) {
      foreach ($links as $data) {
        $b = var_export($data, true);
        //replace string of table name with reference to table object
        $b = preg_replace('#\'table\' => \'([^\']+)\'#','\'table\' => table("$1")',$b);
        echo 'table("'.$table.'")->add_link('.$b.');'."\n\n";
      }
    }
  }

  //turn sql type into field type
  private static function get_type($sql_type) {
    @list($type, $details) = explode(" ", $sql_type, 2);
    $bracket = "";
    if (preg_match('#([^\(]+)\((.+)\)#', $type, $m)) {
      $type = $m[1];
      $bracket = $m[2];
    }
    switch ($type) {
      case "tinyint": case "smallint": case "mediumint": case "int": case "bigint":
      case "float": case "real": case "double":
      case "decimal":
        $ret = array(
          "type" => "number",
          "params" => array()
        );
        
        if (strpos($details, "AUTO_INCREMENT") !== false) {
          $ret['params']['auto_inc'] = 1;
        }
        
        return $ret;
      case "varchar": case "char":
        return array(
          "type" => "string",
          "params" => array("maxlength" => (int)$bracket)
        );
      case "enum":
        return array(
          "type" => "enum",
          "params" => array("options" => str_getcsv($bracket,",","'",'\\'))
        );
      case "longtext":
      case "text":
      case "blob":
        return array(
          "type" => "text",
          "params" => array()
        );
      case "time":
      case "date":
      case "datetime":
        return array(
          "type" => "text",
          "params" => array(),
        );
      break;
      default:
        die("Unsupported type '".$sql_type."'");
      break;
    }
  }
  
  public static function save($data) {
    $ret = null; $first = true; $scanned = false;
    foreach ($data as $table => $kv) {
      list($ks, $vs, $ref) = $kv;

      if (!$vs) continue;
      
      if (!$scanned) {
        $all_primaries = true; $auto_inc = false;
        foreach ($ref->keys as $primary) {
          if ($all_primaries && !in_array($primary, $ks)) {
            $all_primaries = false;
          }
          if (!$auto_inc && isset($ref->fields[$primary]['params']['auto_inc'])) {
            $auto_inc = true;
          }
        }
        $scanned = true;
      }

      if ($all_primaries) {
        if ($auto_inc) {
          $todo = false;
          $sql = "UPDATE `".$table."` SET ";
          $where = " WHERE ";
          while ($ks && $vs) {
            $k = array_shift($ks); $v = array_shift($vs);
            if (in_array($k, $ref->keys)) {
              $where .= "`".$k."` = ".da::escape($v)." AND ";
            } else {
              $todo = true;
              $sql .= "`".$k."` = ".da::escape($v).", ";
            }
          }
          if (!$todo) continue;
          $sql = substr($sql,0,-2).substr($where, 0, -5);
        } else {
          $sql = "INSERT INTO `".$table."` (`".implode("`,`",$ks)."`) VALUES (";
          $dup = ") ON DUPLICATE KEY UPDATE ";
          $dupable = false;
          while ($ks && $vs) {
            $k = array_shift($ks); $v = array_shift($vs);
            $sql .= da::escape($v).", ";
            if (!in_array($k, $ref->keys)) {
              $dup .= "`".$k."` = VALUES(`".$k."`), ";
              $dupable = true;
            }
          }
          if ($dupable) {
            $sql = substr($sql, 0, -2).substr($dup, 0, -2);
          } else {
            $sql = substr($sql, 0, -2).")";
          }
        }
        db::run($sql);
        $ret = db::insertedId();

      } else if (!$auto_inc) {
        db::run("INSERT INTO `".$table."` (
          `".implode("`,`", $ks)."`
        ) VALUES (
          ".implode(",", array_map(array("da", "escape"), $vs))."
        )");
        $ret = db::insertedId();

      } else {
        //auto-inc insert
        if ($first) {
          $first = false;
          db::run("INSERT INTO `".$table."` (`".implode("`,`", $ks)."`) VALUES (".implode(",", array_map(array("da", "escape"), $vs)).")");
          $ret = db::insertedId();
          db::run("SELECT @id := LAST_INSERT_ID()");
        } else {
          db::run("INSERT INTO `".$table."` (`".$ref->keys[0]."`, `".implode("`,`", $ks)."`) VALUES (@id, ".implode(",", array_map(array("da", "escape"), $vs)).")");
        }
      }
    }
    return $ret;
  }
  
  public static function escape($v) {
    return (is_null($v) ? "null" : "'".db::escape($v)."'");
  }
  
  public static function delete($table, $search) {
    $sql = self::recreate_sql($table, $search, "");
    
    $ref = $table;
    do {
      $r = $ref->parent['table'];
      if ($r) $ref = $r;
    } while ($r);
    $q = self::get_select_tree($ref, $search);
    db::run("DELETE `".$table->table."` ".$q['from']." ".$sql['from'].($sql['where'] ? " WHERE ".$sql['where'] : ""));
  }
}
?>