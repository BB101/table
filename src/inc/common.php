<?php
require("startup.php");
require("define.php");

error_reporting(E_ALL & ~E_STRICT);
ini_set("display_errors", true);

//utility functions
function ago($time) {
  $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
  $lengths = array("60","60","24","7","4.33333","12","10");
  $now = time();
  $difference = $now - $time;
  for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++)
    $difference /= $lengths[$j];
  $difference = round($difference);
  if($difference != 1) $periods[$j].= "s";
  return $difference." ".$periods[$j];
}

function until($time) {
  $periods = array("second", "minute", "hour", "day");
  $lengths = array("60","60","24","7");
  $now = time();
  $difference = $time - $now;
  for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++)
    $difference /= $lengths[$j];
  $difference = round($difference);
  if($difference != 1) $periods[$j].= "s";
  if ($difference < 0) return "1 day";
  return $difference." ".$periods[$j];
}

function clean_html($html) {
  //-strong/b,-em/i,-strike,-p,-h4,-ul,-ol,-li,br
  $html = strip_tags($html, "<strong><em><strike><p><h3><h4><ul><ol><li><br>"); //remove bad tags
  $html = preg_replace('#<([^> ]+)[^>]+>(.+?)</\\1>#', "<$1>$2</$1>", $html); //remove bad attributes
  
  return $html;
}

function human_filesize($num, $a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB'), $inc = 1024) {
  $i = 0;
  do { //find the suffix
    $suf = array_shift($a);
  } while ($num >= pow($inc,++$i) && count($a) > 0);
  while ($i-- > 1) $num /= $inc;
  return round($num,2).$suf;
}

//execute a script in the background, see fetch_facebook/flickr/twitter.php
function execInBackground($page) {
  global $cfg;
  $handle = @fopen("http://".$_SERVER['HTTP_HOST'].$cfg['home'].$page, "r");
  @fclose($handle);
}
?>