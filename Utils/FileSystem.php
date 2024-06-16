<?php
namespace Gampil\Utils;

use function Gampil\App\conf;
/*
* @author Naf'an Rizkilah
*
*/
class FileSystem {
  protected static $base_dir = '';
  public static function set_dir($base_dir = '', $relative = true) {
    if($base_dir === '') $base_dir = conf('public_storage_path');
    self::$base_dir = $relative ? rtrim($base_dir, '/') . '/' : rtrim($base_dir, '/').'/';
  }
  // @return array 'absolute path'
  public static function scan_abs($dir, $recursive = false) {
    $dir = rtrim($dir, '/');
    $scanned = [];
    $files = scandir(self::$base_dir . $dir);
    $files = array_filter($files, function($val) {
      return ($val !== '..' or $val !== '.');
    });
    if(!$recursive) {
      foreach($files as $file) {
        $path = self::$base_dir.$dir.'/'.$file;
        $scanned[] = $path;
      }
      return $scanned;
    }
    foreach($files as $file) {
      $path = self::$base_dir . $dir . $file;
      if(is_dir($path)) $scanned += self::scan($dir.'/'.$file);
      else $scanned[] = $path;
    }
    return $scanned;
  }
  // @return array 'relative path'
  public static function scan($dir, $recursive = false) {
    $scanned = [];
    $files = scandir(self::$base_dir . $dir);
    $files = array_filter($files, function($val) {
      return ($val !== '..' or $val !== '.');
    });
    if(!$recursive) {
      foreach($files as $file) $scanned[] = $file;
      return $scanned;
    }
    foreach($files as $file) {
      if(is_dir($file)) $scanned += self::scan($dir.'/'.$file);
      else $scanned[] = $file;
    }
    return $scanned;
  }
  // mengembalikan seluruh isi file
  public static function read($file) {
    return file_get_contents(self::$base_dir . $file);
  }
  // 'write' file (pointer di awal file)
  public static function write($file, $cont = '') {
    return file_put_contents(self::$base_dir . $file, $cont);
  }
  // sama seperti write() hanya saja pointernya di akhir file
  public static function write_append($file, $cont) {
    $file = fopen(self::$base_dir . $file, 'a');
    $ret = fwrite($file, $cont);
    fclose($file);
    return $ret;
  }
  public static function mkdir($path, $perms = 0700, $recurs = true, $use_absolute_path = false) {
    $path = ($use_absolute_path) ? self::$base_dir . $path : $path;
    mkdir($path, $perms, $recurs);
  }
  // move file
  public static function mv($from, $to) {
    copy(self::$base_dir.$from, self::$base_dir.$to);
    unlink(self::$base_dir.$from);
  }
  // copy file
  public static function cp($from, $to) {
    copy(self::$base_dir.$from, self::$base_dir.$to);
  }
  // hapus file / direktori
  public static function rm($file, $recursive = false) {
    $path = self::$base_dir . $file;
    if($recursive) {
      $files = self::scan_abs($path);
      foreach($files as $file) unlink($file);
    }
    unlink($path);
  }
  // shortcut 'file_exists'
  public static function has($path, $use_absolute_path = false) {
    return ($use_absolute_path == true) ? file_exists($path) : file_exists(self::$base_dir.$path);
  }
  // untuk mengupload file dengan beberapa 'rules'. @return string|bool tempat file(dengan direktori) diupload
  public static function upload($file_name, $upload_dir, $rules = []) {
    $rules = array_merge(conf('default_upload_rules'), $rules);
    $name = $_FILES[$file_name]['name'];
    $size = $_FILES[$file_name]['size'];
    $tmp_path = $_FILES[$file_name]['tmp_name'];
    if($_FILES[$file_name]['error'] > 0) {
      session('err', [$file_name => [["empty"]]]);
      return false;// file tidak diupload atau error lainnya
    }
    if($size > $rules['max_size']) {
      $max = round($rules['max_size'] / (1024*1024), 2);
      session('err', [$name => [["too_large", $max]]]);
      return false;// melebihi ukuran maksimum
    }
    $parts = explode('.', $name);
    $ext = strtolower(end($parts));
    if(!str_contains($rules['filter'], ltrim($ext, '.'))) {
      $filter = str_replace(' ', ', ', $rules['filter']);
      session('err', [$name => [["filter", $filter]]]);
      return false;// file tidak termasuk file yang diizinkan untuk diupload
    }
    $unique_name = uniqid(true).'.'.$ext;
    $dir_path = self::$base_dir . $upload_dir;
    $file = $upload_dir . $unique_name;
    if(!file_exists($dir_path)) self::mkdir($dir_path);
    move_uploaded_file($tmp_path, self::$base_dir . $file);
    return $file;
  }
}
