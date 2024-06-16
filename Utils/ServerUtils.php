<?php
namespace Gampil\Utils;

use Gampil\App\Container;
use Gampil\Utils\CSRFToken;

use function Gampil\App\conf;

class ServerUtils {
  private $datas = [];
  private $old_url = '';
  public function __construct() {
    $this->old_url = $_SERVER['HTTP_REFERER'] ?? conf('base_url');
    CSRFToken::clear();
    unset($_POST['_method']);
    $this->datas = $_POST;
  }
  public function input($key = null, $default = null) {
    if ($key === null) {
      return $this->datas;
    }
    return $this->datas[$key] ?? $default;
  }
  public function all() {
    return $this->datas;
  }
  public function has($key) {
    return isset($this->datas[$key]);
  }
  public function get($key) {
    return $this->input($key);
  }
  public function validate_request($rules = []) {
    $errors = [];
    foreach($rules as $field => $rule) {
      $val = $this->input($field);
      foreach($rule as $rule_name => $rule_val) {
        switch($rule_name) {
          case 'required':#bug validation: fatal
            if ($rule_val and empty($val)) {
              $errors[$field][] = [$rule_name];
            }
            break;
          case 'numeric':
            if ($rule_val and !is_numeric($val)) {
              $errors[$field][] = [$rule_name];
            }
            break;
          case 'min':
            if (is_int($rule_val) and strlen($val) < $rule_val) {
              $errors[$field][] = [$rule_name, $rule_val];
            }
            break;
          case 'max':
            if (is_int($rule_val) and strlen($val) > $rule_val) {
              $errors[$field][] = [$rule_name, $rule_val];
            }
            break;
          case 'unique':
            $datas = explode(':', $rule_val);
            $db = Container::get('db');
            $db->query("SELECT `".$datas[1]."` FROM `".$datas[0]."` WHERE `".$datas[1]."` = :col");
            $db->run(['col' => $val]);
            $matches = $db->fetch();
            if (!empty($matches)) {
              $errors[$field][] = ['unique', $val];
            }
            break;
        }
      }
    }
    if ($errors != []) {
      $_SESSION['err'] = $errors;
      $this->back();
    } else {
      return $this->datas;
    }
  }
  public function redirect($url = '') {
    if($url === '') $url = conf('base_url');
    header("Location: " . $url);
  }
  public function back() {
    $this->redirect($this->old_url);
  }
  public function from_json($json) {
    return json_decode($json);
  }
  public function to_json($json) {
    return json_encode($json);
  }
}

# 'err' session format :   'err' => ['input_name' => [ ['err_name', 'additional_info'], ['err_name2', 'additional_info'] ] ]
// function error($raw = false, $all = true) {
//   if(!session()->has('err')) return '';
//   $err = session('err');
//   $err_msg = '';
//   $first = array_key_first($err);
//   session()->forget('err');
//   if($raw == true and $all == true) return $err;
//   if($raw == true and $all == false) return $err[$first];
//   if($all == false) {
//     $first_err = $err[$first];
//     foreach($first_err as $err_name) {
//       $format = [$first] + $err_name;
//       $err_msg .= vsprintf(conf('err_msg')[$err_name[0]] . "\n", $format);
//     }
//     return $err_msg;
//   }
//   $err_arr = [];
//   foreach($err as $field => $err_names) {
//     $err_msg = '';
//     foreach($err_names as $err_name) {
//       $format = [$field] + $err_name;
//       $err_msg .= vsprintf(conf('err_msg')[$err_name[0]] . "\n", $format);
//     }
//     $err_arr[] = $err_msg;
//   }
//   return $err_msg;
// }