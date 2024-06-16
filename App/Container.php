<?php
namespace Gampil\App;

use Exception;
use Gampil\Utils\Auth;

/*
 * @author Naf'an Rizkilah
 *
 * class ini berguna untuk dependency injection
*/
class Container {
  // @var array $datas variabel untuk menyimpan data
  private static $datas = [];
  // @var array $configs variabel untuk menyimpan konfigurasi dari 'app/config.php'. sebuah array asosiatif
  private static $configs = [];
  // memuat environment dari '.env'
  private static function load_env() {
    $file = @fopen(self::get_config('root_path').'.env', 'r');
    if(!$file) return;
    while(($buffer = fgets($file)) !== false) {
      $comment_pos = strpos($buffer, '#');
      if($comment_pos !== false) $buffer = substr($buffer, 0, $comment_pos);
      if($buffer === '') continue;
      if(preg_match("/^[A-Za-z_][A-Za-z0-9_]*=[^=]*$/", $buffer)) {
        $buffer = trim($buffer);
        putenv($buffer);
      }
    }
  }
  public static function add($name, $data) {
    if(self::has($name)) return;
    self::$datas[$name] = $data;
  }
  public static function get($name) {
    if(self::has($name)) return self::$datas[$name];
    throw new Exception("Objects not found !");
  }
  public static function has($name) {
    return (isset(self::$datas[$name]));
  }
  /*public static function remove($name) {*/
  /*  unset(self::$datas[$name]);*/
  /*}*/
  public static function load_config($data) {
    self::$configs = $data;
    self::load_env();
    date_default_timezone_set(env('TIMEZONE'));
    // Auth::load_config($data['auth']);
  }
  public static function get_config($name) {
    return self::$configs[$name];
  }
  public static function has_config($name) {
    return (isset(self::$configs[$name]));
  }
  public static function add_config($name, $val) {
    if(self::has_config($name)) return;
    self::$configs[$name] = $val;
  }
}

// mengembalikan 'value' dari suatu konfigurasi atau null ketika konfigurasi tidak ada
function conf($key, $default = null) {
  return (Container::has_config($key)) ? Container::get_config($key) : $default;
}
// mengembalikan 'value' dari 'environment' atau menetapkan 'nilai' untuk 'environment'
function env($name, $value = null) {
  if($value === null) return getenv($name);
  putenv("$name=$value");
  return $value;
}
