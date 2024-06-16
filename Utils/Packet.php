<?php
namespace Gampil\Utils;

class Packet {
  private $arr = [];
  public $size;
  public function __construct($arr) {
    $this->arr = $arr;
  }
  public function add($val) {
    $this->arr[] = $val;
    $this->size();
  }
  public function set($key, $val) {
    $this->arr[$key] = $val;
    $this->size();
  }
  public function get($key) {
    return $this->arr[$key];
  }
  public function all() {
    return new self($this->arr);
  }
  public function first() {
    $first = array_key_first($this->arr);
    return $this->arr[$first];
  }
  public function first_key() {
    return array_key_first($this->arr);
  }
  public function last() {
    $end = array_key_last($this->arr);
    return $this->arr[$end];
  }
  public function last_key() {
    return array_key_last($this->arr);
  }
  public function shift($count = 1) {
    for ($i=0; $i < $count; $i++) { 
      array_shift($this->arr);
    }
  }
  public function size() {
    $this->size = count($this->arr);
    return $this->size;
  }
  public function pick_random() {
    if(empty($this->arr)) return null;
    $key = array_rand($this->arr);
    return $this->arr[$key];
  }
  public function shuffle($count = 1) {
    for ($i=0; $i < $count; $i++) { 
      shuffle($this->arr);
    }
  }
  public function has_key($key) {
    return isset($this->arr[$key]);
  }
  public function has_val($val) {
    return in_array($val, $this->arr);
  }
  public function values() {
    return array_values($this->arr);
  }
  public function to_arr() {
    return $this->arr;
  }
}
