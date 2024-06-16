<?php
namespace Gampil\Utils\CLI;

class BaseCommand {
  private $outputs = [];
  private $colors = [
    'green' => "\033[38;05;10m",
    'red' => "\033[38;05;160m",
    'blue' => "\033[38;05;12m",
    'yellow' => "\033[38;05;11m",
    'cyan' => "\033[38;05;6m",
    'green_bg' => "\033[48;05;10m",
    'red_bg' => "\033[48;05;160m",
    'blue_bg' => "\033[48;05;12m",
    'yellow_bg' => "\033[48;05;11m",
    'cyan_bg' => "\033[48;05;6m",
    'reset' => "\033[0m"
  ];
  protected $print_later = false;
  protected function print_out_margin() {
    if($this->print_later == true) return;
    $size = count($this->outputs);
    foreach($this->outputs as $key => $text) {
      if($key == 0) {
        echo "\n";
      }
      echo $text;
      if($key == ($size - 1)) {
        echo "\n";
      }
    }
    $this->outputs = [];
  }
  protected function add_output($text) {
    $this->outputs[] = $text;
  }
  protected function color($text, $color) {
    return $this->colors[$color].$text.$this->colors['reset'];
  }
  public function run($args, $opts) {}
}