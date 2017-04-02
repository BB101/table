<?php
class gd {
  private $img, $info;
  
  function __construct($file, $cmds = array()) {
    $this->info = getimagesize($file);
    switch($this->info['mime']) {
      case "image/gif":
        $this->img = imagecreatefromgif($file);
      break;
      case "image/jpeg":
        $this->img = imagecreatefromjpeg($file);
      break;
      case "image/png":
        $this->img = imagecreatefrompng($file);
        imagealphablending($this->img, false);
        imagesavealpha($this->img, true);
      break;  
      default:
        die("Failed to find image type for ".$file);
      break;
    }
    foreach ($cmds as $cmd => $args) {
      call_user_func_array(array($this, $cmd), $args);
    }
  }
  
  function __destruct() {
    if ($this->img) imagedestroy($this->img);
  }

  function resize($w = false, $h = false, $min = true) {
    if ($w == $this->info[0] && $h == $this->info[1]) return;

    if ($min) {
      $wr = $w/$this->info[0];
      $hr = $h/$this->info[1];
      $r = max(array($wr,$hr));
      $nw = round($this->info[0]*$r);
      $nh = round($this->info[1]*$r);
    } else {
      if ($w) {
        $hw = $this->info[1]/$this->info[0];
        $nw = $w;
        $nh = round($nw * $hw);
      }
      if ((!$w && $h) || ($h && $nh > $h)) {
        $wh = $this->info[0]/$this->info[1];
        $nh = $h;
        $nw = round($nh * $wh);
      }
    }

    if ($nw == $this->info[0] && $nh == $this->info[1]) return;
    
    $im2 = imagecreatetruecolor($nw,$nh);
    if ($this->info['mime'] == "image/png") {
      imagealphablending($im2, false);
      imagesavealpha($im2, true);
    }

    imagecopyresampled($im2, $this->img, 0,0, 0,0, $nw,$nh, $this->info[0],$this->info[1]);
    imagedestroy($this->img);
    $this->img = $im2;
    unset($im2);
      
    $this->info[0] = $nw;
    $this->info[1] = $nh;
    
    return $this;
  }

  function crop($l, $t, $r, $b) {
    $w = $r-$l;
    $h = $b-$t;
    $im2 = imagecreatetruecolor($w,$h);
    if ($this->info['mime'] == "image/png") {
      imagealphablending($im2, false);
      imagesavealpha($im2, true);
    }
    imagecopyresampled(
      $im2, $this->img, //from, to
      0, 0,    //dest x,y
      $l, $t,  //source x,y
      $w, $h,  //dest w,h
      $w, $h   //source w,h
    );
    imagedestroy($this->img);
    $this->img = $im2;

    $this->info[0] = $w;
    $this->info[1] = $h;
    
    return $this;
  }
  
  function cropTo($crop, $w, $h) {
    if (!$w) {
      $hr = $h / $this->info[1];
      $w = $this->info[0] * $hr;
    } else if (!$h) {
      $wr = $w / $this->info[0];
      $h = $this->info[1] * $wr;
    }

    $this->resize($w, $h, true);
    
    $wd = $this->info[0] - $w;
    $wo = $wd / 2;
    
    if ($crop == "t") {
      $this->crop($wo, 0, $w + $wo, $h);
    } else if ($crop == "m") {
      $hd = $this->info[1] - $h;
      $this->crop($wo, $hd/2, $w + $wo, $h + $hd/2);
    } else if ($crop == "b") {
      $hd = $this->info[1] - $h;
      $this->crop($wo, $hd, $w + $wo, $this->info[1]);
    }
    return $this;
  }

  function save($file) {
    switch($this->info['mime']) {
      case "image/gif":
        imagegif($this->img, $file);
      break;
      case "image/jpeg":
        imagejpeg($this->img, $file, 90);
      break;
      case "image/png":
        imagepng($this->img, $file);
      break;
      default:
        die("I'll never get here");
      break;
    }
    return $this;
  }
}
?>