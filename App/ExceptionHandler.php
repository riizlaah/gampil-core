<?php
namespace Gampil\App;

class ExceptionHandler {
  private static $query = [];
  // private static $query_data = [];
  private $err_info = '';
  public function __construct($err_msg) {
    $full_backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $full_backtraces = array_filter($full_backtraces, function($val) {
      return isset($val['file']);
    });
    $backtraces = array_slice($full_backtraces, 0, -2);
    $i = 0;
    foreach($backtraces as $backtrace) {
      if(str_contains($backtrace['file'], 'vendor/')) continue;
      if(str_contains($backtrace['file'], 'functions')) continue;
      $i += 1;
      $this->err_info .= $this->color("#$i {$backtrace['file']} (line: {$backtrace['line']}) (function/method: {$backtrace['function']})", 'blueviolet');
    }
    if(strtolower(env('STATUS')) === 'prod') {
      $err_log_msg = date('[Y-m-d H:i:s] : {');
      $i = 0;
      foreach($full_backtraces as $backtrace) {
        $i += 1;
        $err_log_msg .= "\n  #$i {$backtrace['file']} (line: {$backtrace['line']}) (function/method: {$backtrace['function']})";
      }
      $err_log_msg .= "\n  ERR_MSG : $err_msg\n}";
      error_log("\n".$err_log_msg, 3, conf('root_path').'storage/log/gampil.log');
      die('Application Error');
    }
    $this->load_info($err_msg);
  }
  private function color($text, $color = 'black', $tag = 'p') {
    return "<$tag style=\"color: $color;\">$text</$tag>";
  }
  private function load_info($err_msg) {
    $this->err_info .= $this->color($err_msg, 'red');
    $this->err_info .= "<details>";
    $this->err_info .= $this->color('QUERIES : ', 'orange', 'summary');
    $i = 0;
    foreach($this->get_query() as $query_data) {
      $i += 1;
      $this->err_info .= "$i. [<br>    QUERY : {$query_data[0]}<br>    QUERY-DATA : ".serialize($query_data[1])."<br>], <br>";
    }
    $this->err_info .= "</details>";
    $this->print();
  }
  private function print() {
    echo $this->err_info;
    exit;
  }
  public static function add_query($query, $query_data) {
    self::$query[] = [$query, $query_data];
  }
  public static function get_query() {
    return self::$query;
  }
}

