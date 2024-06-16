<?php
namespace Gampil\Utils;

class Flasher {
  public static function set_flash($name, $datas) {
    $_SESSION['flash'][$name] = $datas;
  }
  public static function flash($name) {
    $datas = $_SESSION['flash'][$name] ?? '';
    if(isset($_SESSION['flash'][$name])) unset($_SESSION['flash'][$name]);
    return $datas;
  }
}
