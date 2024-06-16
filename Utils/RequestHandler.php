<?php
namespace Gampil\Utils;

use Gampil\App\Container;
use Gampil\Utils\CSRFToken;

use function Gampil\App\conf;
use function Gampil\App\env;

/*
* @author Naf'an Rizkilah
*
* untuk memvalidasi Request, dan redirecting
*/
class RequestHandler {
  private $datas = [];
  private $errors = [];
  private $validation_rules = [];
  public function __construct() {
    $this->validation_rules = [
      'required' => function($args) {
        if($args['rule_value'] and empty($args['input_value'])) {
          $error = ['required'];
          return $error;
        }
        return true;
      },
      'numeric' => function($args) {
        if($args['rule_value'] and !is_numeric($args['input_value'])) {
          return ['numeric'];
        }
        return true;
      },
      'min' => function($args) {
        if(is_int($args['rule_value']) and strlen($args['input_value']) < $args['rule_value']) {
          return ['min', $args['rule_value']];
        }
        return true;
      },
      'max' => function($args) {
        if(is_int($args['rule_value']) and strlen($args['input_value']) > $args['rule_value']) {
          return ['max', $args['rule_value']];
        }
        return true;
      },
      'between' => function($args){
        $parts = explode(',', $args['rule_value']);
        $min = (int) $parts[0];
        $max = (int) $parts[1];
        $str_len = strlen($args['input_value']);
        if(($str_len < $min) or ($str_len > $max)) return ['between', $min, $max];
        return true;
      },
      'int' => function($args) {
        if(!filter_var($args['input_value'], FILTER_VALIDATE_INT)) return ['int'];
        return true;
      },
      'float' => function($args) {
        $input_val = str_replace(',', '.', $args['input_value']);
        if(!filter_var($input_val, FILTER_VALIDATE_FLOAT)) return ['float'];
      },
      'accepted' => function($args) {
        $acc = 'true false 0 1 ok yes accept agree setuju';
        if(!str_contains($acc, trim($args['input_value']))) return ['accepted'];
        return true;
      },
      'url' => function($args) {
        if(!filter_var($args['input_value'], FILTER_VALIDATE_URL)) return ['url', $args['input_value']];
        return true;
      },
      'email' => function($args) {
        if(!filter_var($args['input_value'], FILTER_VALIDATE_EMAIL)) return ['email', $args['input_value']];
        return true;
      },
      'contains' => function($args) {
        $contains = explode(',', $args['rule_value']);
        if(!in_array($args['input_value'], $contains)) return ['contains', $args['input_value'], implode(', ', $contains)];
        return true;
      },
      'unique' => function($args) {
        $datas = explode(':', $args['rule_value']);
        $db = Container::get('db');
        $db->query("SELECT {$datas[1]} FROM {$datas[0]} WHERE {$datas[1]} = :col");
        $db->run(['col' => $args['input_value']]);
        $found = $db->fetch();
        if(is_bool($found)) return true;
        return ['unique', $args['input_value']];
      }
    ];
    $this->validation_rules += conf('custom_validation');
    CSRFToken::clear();
    unset($_POST['_method']);
    $this->datas = $_POST;
  }
  public function all() {
    return $this->datas;
  }
  public function has($key) {
    return isset($this->datas[$key]);
  }
  public function get($key, $default = null) {
    return $this->datas[$key] ?? $default;
  }
  // untuk validasi request. @return array|void mengembalikan $datas jika berhasil, mengalihkan ke halaman sebelumnya jika tidak berhasil
  public function validate_request($rules = []) {
    // contoh :
    // $rules = [
    //   'field' => [
    //     'required',
    //     'min=5'
    //   ]
    // 
    $datas = [];
    foreach($rules as $input_field => $field_rules) {
      $input_value = $this->get($input_field);
      if(isset($this->datas[$input_field])) $datas[$input_field] = $datas;
      foreach($field_rules as $rule) {
        $parts = explode('=', $rule);
        $rule_name = $parts[0];
        $rule_value = $parts[1] ?? true;
        $this->process_rule($rule_name, $rule_value, $input_field, $input_value);
      }
    }
    if ($this->errors !== []) {
      $_SESSION['err'] = $this->errors;
      $_SESSION['prev_input'] = $this->datas;
      redirect();
    } else return $this->datas;
  }
  private function process_rule($rule_name, $rule_value, $input_field, $input_value) {
    $rule_value = (is_numeric($rule_value)) ? ((int) $rule_value) : $rule_value;
    $args = ['rule_name' => $rule_name, 'rule_value' => $rule_value, 'input_field' => $input_field, 'input_value' => $input_value];
    if(!isset($this->validation_rules[$rule_name])) {
      trigger_error("validation rule '$rule_name' is not exist", E_USER_ERROR);
    }
    $ret = $this->validation_rules[$rule_name]($args);
    if(is_bool($ret)) return;
    $this->errors[$input_field][] = $ret;
  }
  public function decode_json($json) {
    return json_decode($json);
  }
  public function to_json($json) {
    return json_encode($json);
  }
}
