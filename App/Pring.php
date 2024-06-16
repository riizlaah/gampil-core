<?php
namespace Gampil\App;

class Pring {
  protected $view_path = 'resources/views/';
  protected $cache_path = 'storage/cache/';
  protected $view_content = '';
  protected $shortcuts = [
    "/\{\{(.+?)\}\}/" => "<?= htmlspecialchars((string) $1) ?>",
    "/\{(.+?)\}/" => "<?=$1?>",
    "/\#foreach\((.*?)\)/" => "<?php foreach($1): ?>",
    "/\#endforeach/" => "<?php endforeach; ?>",
    "/\#if\((.*)\)/" => "<?php if($1): ?>",
    "/\#elif\((.*)\)/" => "<?php elseif($1): ?>",
    "/\#else/" => "<?php else: ?>",
    "/\#endif/" => "<?php endif; ?>",
    "/\#csrf/" => "<?= csrf_token() ?>",
    "/#method\((.*?)\)/" => "<input type='hidden' name='_method' value=$1>",
    "/#err\('([^']*)', '([^']*)'\)$/" => "<?= (has_err('$1')) ? '$2' : '' ?>",
    "/#if_err\((.*)\)/" => "<?php if(has_err($1)): ?>",
    "/#end_err/" => "<?php endif; ?>",
    "/#previn\((.*?)\)/" => "<?= prev_input($1) ?>"
    // "/#session\((.*?)\)/" => "php if(session()->has($1)): ",
    // "/#endsession/"
  ];
  public function __construct() {
    $this->view_path = conf('root_path') . $this->view_path;
    $this->cache_path = conf('root_path') . $this->cache_path;
  }
  public function render($view, $datas = [], $extend = '') {
    $view_file = $this->view_path.$view.'.php';
    $cache_file = $this->cache_path.str_replace('/', '_', $view).'.php';
    if($extend != '') $this->extend($extend, $view_file, $cache_file);
    if(!$this->is_cache_valid($view_file, $cache_file) and $extend == '') $this->compile($view_file, $cache_file);
    extract($datas);
    ob_start();
    require_once $cache_file;
    echo ob_get_clean();
  }
  protected function extend($extend, $view_file, $cache_file) {
    $extended_file = $this->view_path.$extend.'.php';
    $extended_cache = $this->cache_path.str_replace('/', '_', $extend).'.php';
    $this->compile($extended_file, $extended_cache);
    if($this->is_cache_valid($view_file, $cache_file, $extend)) return;
    $content = file_get_contents($extended_cache);
    $view_content = $this->compile_content(file_get_contents($view_file));
    $content = preg_replace("/\#content/", $view_content, $content);
    file_put_contents($cache_file, $content);
  }
  protected function compile($view_file, $cache_path) {
    $content = $this->compile_content(file_get_contents($view_file));
    file_put_contents($cache_path, $content);
  }
  protected function compile_content($content) {
    foreach($this->shortcuts as $pattern => $replace) {
      $content = preg_replace($pattern, $replace, $content);
    }
    return $content;
  }
  protected function is_cache_valid($view, $cache, $extend = '') {
    if($extend == '') {
      if(!file_exists($cache) or (filemtime($view) > filemtime($cache))) return false;
      return true;
    }
    if(!file_exists($cache) or (filemtime($view) > filemtime($cache))) return false;
    $extend_cache = $this->cache_path.str_replace('/', '_', $extend).'.php';
    $extend_view = $this->view_path.$extend.'.php';
    if(!file_exists($extend) or (filemtime($extend_view) > filemtime($extend_cache))) return false;
    return true;
  }
}
