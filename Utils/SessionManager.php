<?php
namespace Gampil\Utils;
/*
* @author Naf'an Rizkilah
*
* untuk mengelola sesi dengan mudah
*/
class SessionManager {
  public static function set($key, $val) {
    $_SESSION[$key] = $val;
  }
  public static function add($key, $val) {
    $_SESSION[$key][] = $val;
  }
  public static function get($key) {
    return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
  }
  public static function has($key_path) {
    $keys = explode('>', $key_path);
    $current = $_SESSION;
    foreach($keys as $key) {
      if(isset($current[$key])) $current = $current[$key];
      else return false;
    }
    return true;
  }
  public static function forget($key) {
    if (isset($_SESSION[$key])) unset($_SESSION[$key]);
  }
  public static function flush() {
    session_destroy();
  }
  public static function invalidate() {
    $_SESSION = [];
    session_regenerate_id(true);
  }
}

function session($key = null, $val = null) {
  if($key !== null and $val !== null and !SessionManager::has($key)) return SessionManager::set($key, $val);
  if($key === null) return new SessionManager;
  return SessionManager::get($key);
}
