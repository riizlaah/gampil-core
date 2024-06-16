<?php
namespace Gampil\Utils\CLI;

use Gampil\Utils\CLI\Commands;
use Gampil\Utils\FileSystem;

/*
* @author Naf'an Rizkilah
*
* untuk berinteraksi dengan CLI
*/
class CommandLineTool {
  public function __construct() {
    // supaya ketika membuat controller, model atau table tidak error
    FileSystem::set_dir(dirname(dirname(__DIR__)).'/template/');
  }
  public function run($args) {
    $commands = [];
    array_shift($args);
    $parsed = $this->parse_args($args);
    $args = $parsed[0];
    $commands[0] = $args[0] ?? null;
    if($commands[0] == null) return $this->display_help();
    if(isset($args[1])) $commands[1] = $args[1];
    unset($args[0], $args[1], $raw);
    $full_cmd = implode('_', $commands);
    $cmd_obj = new Commands($parsed[1]);// $parsed[1] adalah 'option'
    call_user_func_array([$cmd_obj, $full_cmd], $args);
  }
  private function display_help() {
    echo "\033[32mAvailable Command : \n";
    $cmds = get_class_methods(Commands::class);
    unset($cmds[0]);
    foreach($cmds as $method) {
      echo "- ".str_replace('_', ' ', $method)."\n";
    }
    echo "\033[0m";
  }
  private function parse_args($in_args) {
    $args = [];
    $opts = [];
    $curr_opt = null;
    foreach($in_args as $arg) {
      if(strpos($arg, '-') === 0) {
        $arg = ltrim($arg, '-');
        if(strpos($arg, '=') !== false) {
          list($opt, $value) = explode('=', $arg, 2);
          $opts[$opt] = $value;
        } else {
          $curr_opt = $arg;
          $opts[$curr_opt] = true;
        }
      } else {
        if($curr_opt) {
          $opts[$curr_opt] = $arg;
          $curr_opt = null;
        } else {
          $args[] = $arg;
        }
      }
    }
    return [$args, $opts];
  }
}
