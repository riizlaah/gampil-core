<?php
namespace Gampil\Database;

use Gampil\App\Container;
use Gampil\Database\Database;
/*
* @author Naf'an Rizkilah
*
* class 'Table' untuk membuat tabel dengan mudah
*/
class Table {
  private ?Database $db = null;
  private $table = null;
  private $cols = [];
  private $foreign_keys = [];
  private function get_last() {
    return array_key_last($this->cols);
  }
  public function __construct($table) {
    $this->table = $table;
    $this->db = Container::get('db');
  }
  public function id($col = 'id') {
    $this->cols[$col] = 'INTEGER PRIMARY KEY AUTOINCREMENT ';
    return $this;
  }
  public function text($col) {
    $this->cols[$col] = 'TEXT ';
    return $this;
  }
  public function int($col) {
    $this->cols[$col] = 'INTEGER ';
    return $this;
  }
  public function blob($col) {
    $this->cols[$col] = 'BLOB ';
    return $this;
  }
  public function unique() {
    $last = $this->get_last();
    $this->cols[$last] .= 'UNIQUE ';
    return $this;
  }
  public function not_null($nullable = false) {
    $last = $this->get_last();
    $this->cols[$last] .= ($nullable === false) ? 'NOT NULL ' : 'NULL ';
    return $this;
  }
  public function default($val) {
    $last = $this->get_last();
    $this->cols[$last] .= 'DEFAULT '.$val.' ';
    return $this;
  }
  public function primary_key() {
    $last = $this->get_last();
    $this->cols[$last] .= 'PRIMARY KEY ';
    return $this;
  }
  public function foreign_key($ref_table, $ref_column = null) {
    $last = $this->get_last();
    if(is_null($ref_column)) {
      $parts = explode('_', $ref_table);
      $ref_table = $parts[0];
      $ref_column = $parts[1];
    }
    $this->foreign_keys[] = "FOREIGN KEY ($last) REFERENCES $ref_table($ref_column)";
    return $this;
  }
  public function auto_increment() {
    $last = $this->get_last();
    $this->cols[$last] .= 'AUTOINCREMENT ';
    return $this;
  }
  public function timestamp() {
    $this->text('created_at');
    $this->text('updated_at');
    return $this;
  }
  public function soft_deleted() {
    $this->text('deleted_at');
    return $this;
  }
  public function create() {
    $query = 'CREATE TABLE IF NOT EXISTS "'.$this->table.'" (';
    foreach($this->cols as $name => $type) $query .= '"'.$name.'" '.$type.',';
    foreach($this->foreign_keys as $foreign_key) $query .= $foreign_key . ',';
    $query = rtrim($query, ', ') . ")";
    $this->db->exec($query);
  }
  public function drop() {
    $query = 'DROP TABLE IF EXISTS "'.$this->table.'"';
    $this->db->exec($query);
  }
}
