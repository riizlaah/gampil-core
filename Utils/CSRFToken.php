<?php
namespace Gampil\Utils;
/*
* @author Naf'an Rzikilah
*
* untuk menangani CSRF
*/
class CSRFToken {
  public static function generate() {
    // membuat token
    if(!isset($_SESSION['csrf-token'])) $_SESSION['csrf-token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf-token'];
  }
  public static function validate($token) {
    if(!isset($_SESSION['csrf-token'])) return false;
    if(!hash_equals($_SESSION['csrf-token'], $token)) return false;
    self::clear();
    return true;
  }
  public static function csrf() {
    $token = self::generate();
    return '<input type="hidden" name="csrf-token" value="'.$token.'">';
  }
  public static function clear() {
    unset($_SESSION['csrf-token']);
    unset($_POST['csrf-token']);
  }
}
