<?php
namespace Gampil\Utils;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class Wadah {
  private $arr = [];
  private $data = [];
  public function __construct($arr) {
    $this->arr = $arr;
    $this->first_key();
  }
  public function __get($property) {
    if(isset($this->data[$property])) return $this->data[$property];
    return null;
  }
  private function update() {
    $this->size();
    $this->first_key();
    $this->last_key();
  }
  public function add($val) {
    $this->arr[] = $val;
    $this->update();
  }
  public function set($key, $val) {
    $this->arr[$key] = $val;
    $this->update();
  }
  public function get($key, $default = null) {
    return $this->arr[$key] ?? $default;
  }
  public function all() {
    return new self($this->arr);
  }
  public function first() {
    $this->first_key();
    return $this->data['first'];
  }
  public function first_key() {
    $first_key = array_key_first($this->arr);
    $this->data['first'] = $this->arr[$first_key];
    return $first_key;
  }
  public function last() {
    $this->last_key();
    return $this->data['last'];
  }
  public function last_key() {
    $last_key = array_key_first($this->arr);
    $this->data['last'] = $this->arr[$last_key];
    return $last_key;
  }
  public function shift($count = 1) {
    for ($i=0; $i < $count; $i++) { 
      array_shift($this->arr);
    }
    $this->update();
  }
  public function size() {
    $this->data['size'] = count($this->arr);
    return $this->data['size'];
  }
  public function pick_random($how_much = 1) {
    if(empty($this->arr)) return null;
    $key = array_rand($this->arr, $how_much);
    $ret = null;
    if(is_array($key)) foreach($key as $idx) $ret[] = $this->arr[$idx];
    else $ret = $this->arr[$key];
    return $ret;
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
  public function keys() {
    return array_keys($this->arr);
  }
  public function remove($key) {
    if(!isset($this->arr[$key])) return;
    unset($this->arr[$key]);
    $this->update();
  }
  public function reduce($callback, $initial = null) {
    $this->arr = array_reduce($this->arr, $callback, $initial);
    return $this->arr;
  }
  public function filter($callback) {
    $this->arr = array_filter($this->arr, $callback, ARRAY_FILTER_USE_BOTH);
    $this->update();
  }
  public function map($callback) {
    $this->arr = array_map($callback, $this->arr);
    $this->update();
  }
  public function merge($array) {
    $this->arr = array_merge($this->arr, $array);
    $this->update();
  }
  public function slice($offset, $length = null) {
    return array_slice($this->arr, $offset, $length);
  }
  public function reverse() {
    $this->arr = array_reverse($this->arr, true);
    $this->update();
  }
  public function find_key($val) {
    return array_search($val, $this->arr);
  }
  public function sort($descending = false) {
    if($descending) {
      arsort($this->arr);
      $this->update();
      return;
    }
    asort($this->arr);
    $this->update();
  }
  public function unique() {
    $this->arr = array_unique($this->arr);
    $this->update();
  }
  public function chunk($size) {
    return array_chunk($this->arr, $size);
  }
  public function combine($keys) {
    if(count($keys) !== count($this->arr)) return;
    $this->arr = array_combine($keys, $this->arr);
    $this->update();
  }
  public function intersect($arr) {
    $this->arr = array_intersect($this->arr, $arr);
    $this->update();
  }
  public function diff($arr) {
    $this->arr = array_diff($this->arr, $arr);
    $this->update();
  }
  public function flatten() {
    $this->arr = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($this->arr)), false);
    $this->update();
  }
  public function sum() {
    return array_sum($this->arr);
  }
  public function avg() {
    return $this->size() > 0 ? array_sum($this->arr) / $this->size : 0;
  }
  public function max() {
    return (!empty($this->arr)) ? max($this->arr) : null;
  }
  public function min() {
    return (!empty($this->arr)) ? min($this->arr) : null;
  }
  public function median() {
    $count = $this->size();
    if($count === 0) return null;
    $values = $this->arr;
    sort($values);
    $mid = (int) ($count / 2);
    return (($count % 2) === 0) ? ($values[$mid]) / 2 : $values[$mid];
  }
  public function to_arr() {
    return $this->arr;
  }
}
