<?php
namespace Gampil\App;

use Gampil\App\Pring;
use Gampil\App\Container;
use Gampil\Utils\CSRFToken;
use Gampil\Database\Database;
use Gampil\Utils\FileSystem;
use Gampil\Utils\RequestHandler;
use Gampil\App\ExceptionHandler;

/*
* @author Naf'an Rizkilah
*
* Inti dari Aplikasi. memproses rute dan menangani CSRF
*/
class Core {
  private $routes = [];
  public function __construct($config_data) {
    $this->ready($config_data);
    $this->process();
  }
  // memproses rute, mengelola CSRF , memanggil method $controller dari data yang dihasilkan, menngembalikan respon 404
  // jika sumber daya tidak ada
  private function process() {
    $data = $this->get_url_data();
    $this->csrf_token();
    if (!file_exists(conf('root_path')."app/controllers/" . $data[0] . ".php")) resp_code(404, true);
    Container::add('db', new Database);
    require_once conf('root_path')."app/controllers/" . $data[0] . ".php";
    $controller = new $data[0](new Pring, new RequestHandler);
    call_user_func_array([$controller, $data[1]], $data[2]);
  }
  // mendapatkan uri, method(jika ada $_POST['_method'] gunakan variabel tersebut, jika tidak ada, pakai $_SERVER['REQUEST_METHOD']),
  // membuat pola regex dari setiap rute untuk dicocokkan dengan $uri mengembalikan data dari rute jika rute cocok dengan uri,
  // jika tidak ada rute yang cocok dengan $uri maka kembalikan respon 404
  private function get_url_data() {
    $uri = filter_var($_SERVER["REQUEST_URI"], FILTER_SANITIZE_URL);
    $data_pos = strpos($uri, '?');
    if($data_pos !== false) $uri = substr($uri, 0, $data_pos);
    $method = $_POST['_method'] ?? $_SERVER["REQUEST_METHOD"];
    $patterns = [];
    foreach ($this->routes as $route => $info) {
      if(!isset($info[1])) $info[1] = "GET";
      $pattern = preg_replace("/\/{(.*?)}/", "/(.*)?", $route);
      $pattern = str_replace("/", "\/", $pattern);
      $pattern = "/^" . $pattern . "$/";
      $patterns[] = $pattern;
      // var_dump($pattern, $uri);
      if (preg_match($pattern, $uri, $matches) and $info[1] === $method) {
        array_shift($matches);
        $handler_info = explode(":", $info[0]);
        $result = [$handler_info[0], $handler_info[1], $matches];
        return $result;
      }
    }
    resp_code(404, true);
  }
  // menangani CSRF
  private function csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      if (!isset($_POST['csrf-token'])) resp_code(419, true);
      if (!CSRFToken::validate($_POST['csrf-token'])) resp_code(419, true);
    }
  }
  // menyiapkan sumber daya yang diperlukan (konfigurasi, rute dan zona waktu)
  private function ready($config_data) {
    Container::load_config($config_data);
    $this->routes = require_once conf('root_path').'app/routes.php';
    FileSystem::set_dir();
  }
}

// wrapper untuk 'http_response_code' dengan opsi untuk menghentikan eksekusi skrip
function resp_code($code = 0, $die = false) {
  if($code > 0) http_response_code($code);
  if($die === true) die;
}
