<?php
class soundex extends table_row {
  private static $stop_words;

  public static function getIds($words, $createIds = true) {
    $soundexed = self::get_soundex($words);
    $in = ""; $first = true;
    foreach ($soundexed as $soundex) {
      if ($first) {
        $first = false;
      } else {
        $in .= ", ";
      }
      $in .= da::escape($soundex);
    }
    if ($in) {
      $rs = table("soundex")->get("`soundex` IN (".$in.")");
      $ret = array(); $soundexes = array();
      foreach ($rs as $r) {
        $ret[] = $r->id;
        $soundexes[] = $r->soundex;
      }
      if ($createIds) {
        foreach ($soundexed as $soundex) {
          if (!in_array($soundex, $soundexes)) {
            $new_id = table("soundex")->save(array("soundex" => $soundex));
            $ret[] = $new_id;
            $soundexes[] = $soundex;
          }
        }
      }
      return $ret;
    }
    return array();
  }
  
  public static function get_soundex($words) {
    return array_unique(
      array_map("soundex",
        self::filter_words(
          preg_split("#\s+#",
            preg_replace("#[^ A-Za-z']+#", " ", $words),
            -1,
            PREG_SPLIT_NO_EMPTY
          )
        )
      )
    );
  }

  private static function get_stop_words() {
    if (self::$stop_words != null) return;
    self::$stop_words = db::getCol("
      SELECT word
      FROM search_stopwords
    ");
  }
  
  public static function filter_words($words) {
    self::get_stop_words();
    
    $ret = array();
    foreach ($words as $w) {
      if (strlen($w) <= 2) continue;
      if (in_array($w, self::$stop_words)) continue;
      $ret[] = $w;
    }
    return $ret;
  }
  
  public static function prune_orphans() {
    db::run("
      DELETE soundex FROM soundex
      LEFT JOIN employer_soundex j0 ON
        j0.soundex_id = soundex.id
      LEFT JOIN job_soundex j1 ON
        j1.soundex_id = soundex.id
      WHERE
        j0.employer_id IS NULL AND
        j1.job_id IS NULL
    ");
  }
}
?>