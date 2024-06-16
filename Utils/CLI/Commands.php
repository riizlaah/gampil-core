<?php
namespace Gampil\Utils\CLI;

use Gampil\App\Container;
use Gampil\Utils\FileSystem;
use Gampil\Database\Database;
use function Gampil\App\conf;
use function Gampil\App\env;
/*
* @author Naf'an Rizkilah
*
* untuk memulai development server, dan membuat file controller, model dan lain-lain.
*/
class Commands {
  private $outputs = [];
  private $colors = [
    'white' => "\033[38;05;15m",
    'normal' => "\033[38;05;7m",
    'green' => "\033[38;05;10m",
    'red' => "\033[38;05;160m",
    'blue' => "\033[38;05;12m",
    'light-blue' => "\033[38;05;14m",
    'yellow' => "\033[38;05;11m",
    'cyan' => "\033[38;05;6m",
    'purple' => "\033[38;05;13m",
    'orange' => "\033[38;05;202m",
    'white_bg' => "\033[48;05;15m\033[38;05;0m",
    'normal_bg' => "\033[48;05;7m\033[38;05;0m",
    'green_bg' => "\033[48;05;10m\033[38;05;8m",
    'red_bg' => "\033[48;05;160m",
    'blue_bg' => "\033[48;05;12m",
    'light-blue-bg' => "\033[48;05;14m",
    'yellow_bg' => "\033[48;05;11m\033[38;05;8m",
    'cyan_bg' => "\033[48;05;6m",
    'purple_bg' => "\033[48;05;13m",
    'orange_bg' => "\033[48;05;202m",
    'reset' => "\033[0m"
  ];
  private $print_later = false;
  private $opt = [];
  private $template_path;
  public function __construct($opt) {
    $this->opt = $opt;
    $this->template_path = dirname(dirname(__DIR__)).'/template/';
  }
  private function print_out_margin() {
    if($this->print_later == true) return;
    $size = count($this->outputs);
    foreach($this->outputs as $key => $text) {
      if($key == 0) echo "\n";
      echo $text;
      if($key == ($size - 1)) echo "\n";
    }
    $this->outputs = [];
  }
  private function add_output($text, $do_echo = false) {
    $this->outputs[] = $text;
    if($do_echo) $this->print_out_margin();
  }
  private function color($text, $color) {return $this->colors[$color].$text.$this->colors['reset'];}
  // untuk memfilter output dari 'PHP development server' menjadi lebih simpel
  private function filter_output($output) {
    $lines =  explode("\n", $output);
    $filtered_lines = [];
    foreach($lines as $line) {
      if(strpos($line, "Accepted") !== false or strpos($line, "Closing") !== false) continue;
      if(strpos($line, "Development Server") !== false) $line = $this->color("Development Server started.", 'green')." (".env('BASE_URL').")\nPress 'Ctrl'+C to exit\n";
      if(preg_match('/\[(\d{3})\]: (\w+) (.+)/', $line, $matches)) {
        $code = intval($matches[1]);
        $stat = match(true) {
          ($code > 100 and $code < 199) => $matches[1],
          ($code > 199 and $code < 299) => $this->color(" ".$matches[1]." ", 'green_bg'),
          ($code > 299 and $code < 399) => $this->color(" ".$matches[1]." ", 'blue_bg'),
          ($code > 399 and $code < 499) => $this->color(" ".$matches[1]." ", 'yellow_bg'),
          ($code > 499 and $code < 599) => $this->color(" ".$matches[1]." ", 'red_bg'),
        };
        $line = $stat." ".$this->color(" ".$matches[2]." ", 'cyan_bg')." ".$matches[3];
      }
      $filtered_lines[] = $line;
    }
    return implode("\n", $filtered_lines);
  }
  // memulai 'PHP Development Server'
  public function start() {
    $desc_spec = [
      ["pipe", "r"],
      ["pipe", "w"],
      ["pipe", "w"]
    ];
    $server_addr = ltrim(env('BASE_URL'), 'http://');
    $proc = proc_open("php -S ".$server_addr." -t './public/'", $desc_spec, $pipes);
    if(is_resource($proc)) {
      stream_set_blocking($pipes[1], 0);
      stream_set_blocking($pipes[2], 0);
      while(true) {
        // menangkap output
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        $num_ch_streams = stream_select($read, $write, $except, 0, 50000);
        if($num_ch_streams === false) break;
        elseif($num_ch_streams > 0) {
          foreach($read as $pipe) {
            $output = stream_get_contents($pipe);
            if($output !== false and $output !== '') {
              $output = $this->filter_output($output);
              echo $output;
            }
          }
        }
        $stat = proc_get_status($proc);
        if(!$stat['running']) break;
      }
      fclose($pipes[0]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($proc);
    }
  }
  public function create_controller($name = '') {
    $tmp = FileSystem::read('controller.txt');
    if($name == '') {
      $name = readline('Insert Controller name: ');
      if($name == '') die('Failed');
    }
    $tmp = str_replace('{name}', $name, $tmp);
    FileSystem::set_dir('app/controllers/');
    if(FileSystem::write($name.'.php', $tmp) != false) $this->add_output($this->color("Succesfully making Controller: $name\n", 'green'));
    else $this->add_output($this->color("Failed.\n", 'red'));
    $this->print_out_margin();
    $model_name = '';
    // untuk mengecek 'option' 'm', lakukan create_model jika ada opsi
    if(isset($this->opt['m'])) {
      FileSystem::set_dir($this->template_path);
      if(is_string($this->opt['m'])) {
        $model_name = $this->opt['m'];
        $this->create_model($this->opt['m']);
      }
      if(is_bool($this->opt['m']) and $this->opt['m'] === true) {
        $model_name = str_replace('_controller', '', $name);
        $model_name = str_replace('_Controller', '', $model_name);
        $model_name = str_replace('Controller', '', $model_name);
        $model_name = str_replace('controller', '', $model_name);
        $this->create_model($model_name);
      }
    }
  }
  public function create_model($name = '') {
    $template = FileSystem::read('model.txt');
    if($name == '') {
      $name = readline("Insert model name : ");
      if($name == '') die('Failed');
    }
    $template = str_replace('{name}', $name, $template);
    FileSystem::set_dir('app/models/');
    if(FileSystem::write($name.'.php', $template) != false) $this->add_output($this->color("Succesfully making Model: $name\n", 'green'));
    else $this->add_output($this->color("Failed.\n", 'red'));
    $this->print_out_margin();
    // untuk mengecek jika ada opsi 't', lakukan create_table jika ada opsi
    if(isset($this->opt['t'])) {
      FileSystem::set_dir($this->template_path);
      if(is_string($this->opt['t'])) $this->create_table($this->opt['t']);
      if(is_bool($this->opt['t']) and $this->opt['t'] === true) $this->create_table($name);
    }
  }
  public function create_table($name = '') {
    $template = FileSystem::read('table.txt');
    if($name == '') {
      $name = readline("Insert table name : ");
      if($name == '') die('Failed');
    }
    $template = str_replace('{name}', $name, $template);
    FileSystem::set_dir('database/tables/');
    if(FileSystem::write(date('Y-m-d_H:i:s').'_'.$name.'.php', $template) != false) $this->add_output($this->color("Succesfully making Table.\n", 'green'));
    else $this->add_output($this->color("Failed.\n", 'red'));
    $this->print_out_margin();
  }
  // membuat tabel-tabel sesuai dengan file-file di 'database/tables' (migration)
  public function table_create() {
    FileSystem::set_dir('database/');
    Container::add('db', new Database);
    $files = array_filter(FileSystem::scan_abs('tables'), function($val){return str_contains($val, '.php');});
    foreach($files as $file) {
      include_once $file;
      $class_name = explode('_', $file);
      $class_name = rtrim(end($class_name), '.php');
      if(class_exists($class_name)) {
        $obj = new $class_name;
        $obj->on_create();
        $this->add_output($this->color("Creating table: ".$class_name."\n", 'green'));
      }
    }
    // untuk mengecek jika ada opsi 's', lakukan 'seeding' jika ada opsi
    if(isset($this->opt['s'])) $this->table_seed();
    $this->print_out_margin();
  }
  // menghapus semua tabel
  public function table_drop() {
    $ctype = env('CONN_TYPE');
    FileSystem::set_dir('database/');
    Container::add('db', new Database);
    $db = Container::get('db');
    $query = match(true) {
      $ctype === 'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table'",
      $ctype === 'mysql' => "SHOW TABLES"
    };
    $db->query($query);
    $db->run();
    $tables = $db->fetch_all();
    foreach($tables as $table) {
      foreach($table as $table2) {
        if($table2 === 'sqlite_sequence') continue;
        $db->exec("DROP TABLE IF EXISTS $table2");
      }
    }
    $this->add_output($this->color("Dropping tables...\n", 'red'));
    $this->print_out_margin();
  }
  // shortcut untuk table_drop + table_create
  public function table_recreate() {
    $this->print_later = true;
    $this->table_drop();
    $this->table_create();
    $this->print_later = false;
    $this->print_out_margin();
  }
  public function table_seed() {
    // seeding database
    Container::add('db', new Database);
    require_once conf('root_path').'database/seeders.php';
    $this->add_output($this->color('Successfully seeding table.', 'green')."\n");
    $this->print_out_margin();
  }
  public function route_list() {
    echo "";
    $routes = require_once 'app/routes.php';
    $total = 0;
    foreach($routes as $route => $info) {
      if(!isset($info[1])) $info[1] = 'GET';
      $data = explode(':', $info[0]);
      $matches = [];
      $params = [];
      if(preg_match_all('/\{([^}]+)\}/', $route, $matches)) {
        foreach($matches[1] as $match) $params[] = '$'.$match;
      }
      $params = implode(', ', $params);
      echo "- ".$this->color($route, 'cyan')." | [".$this->color($info[1], 'yellow')."] | ".$this->color($data[0], 'light-blue')."->".$this->color($data[1], 'orange')."(".$this->color($params, 'white').")\n";
      $total += 1;
    }
    echo "Total Routes: ".$this->color($total, 'green')."\n";
  }
  // public function gen_env() {
  //   $env_file = conf('root_path').'.env';
  //   $content = FileSystem::read('env.txt');
  //  FileSystem::set_dir(conf('root_path'));
  //   if(file_exists($env_file)) {
  //     echo "File already exists. are you sure to ".$this->color("OVERRIDE it?\n", 'red');
  //     $confirm = readline("y/n :");
  //     $confirm = trim(strtolower($confirm));
  //     if(!str_contains('yn', $confirm)) die('you must answer \'y\' or \'n\'');
  //     if($confirm === 'n' or $confirm === 'yn') die("Abort\n");
  //     FileSystem::write($env_file, $content);
  //     echo $this->color("\nFile .env overrided.", 'yellow');
  //     $this->gen_key();
  //     return;
  //   }
  //   FileSystem::write($env_file, $content);
  //   $this->gen_key();
  //   $this->add_output($this->color('.env file successfully generated.', 'green'), true);
  // }
  // public function gen_key() {
  //   $old_key = env('APP_KEY');
  //   $env_file = conf('root_path').'.env';
  //   $content = file_get_contents($env_file);
  //   $gen_key = 'base64:'.base64_encode(random_bytes(32));
  //   if(!empty($old_key)) {
  //     $content = preg_replace("/^".preg_quote('APP_KEY', '/')."=.*/m", "APP_KEY=$gen_key", $content);
  //   } else {
  //     $content = "APP_KEY=$gen_key\n".$content;
  //   }
  //   $file = fopen($env_file, 'w');
  //   fwrite($file, $content);
  //   fclose($file);
  //   $this->add_output($this->color('Key has been generated.', 'green'), true);
  // }
}
