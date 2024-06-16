<?php
namespace Gampil\App;

use Gampil\App\Pring;
use Gampil\Utils\RequestHandler;
use Gampil\App\Model;

class Controller {
  // Pring view compiler untuk mengkompilasi suatu view
  protected Pring $pring;
  // Request Handler untuk mengelola request
  protected RequestHandler $rhand;
  public function __construct($pring, $req_h) {
    $this->pring = $pring;
    $this->rhand = $req_h;
  }
  // merender sebuah $view dengan suatu $datas, jika $extend tidak kosong, maka $view akan meng'extend' view $extend
  protected function view($view_name, $datas = [], $extend = '') {
    $this->pring->render($view_name, $datas, $extend);
    session()->forget('prev_input');
    session()->forget('err');
  }
  // mengembalikan sebuah model
  protected function model($model_name): Model {
    $fqn = "\\models\\".$model_name;
    if(!class_exists($fqn)) trigger_error("Model '$model_name' is not exist!", E_USER_ERROR);
    return new $fqn;
  }
}



