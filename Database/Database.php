<?php
namespace Gampil\Database;

use Gampil\App\ExceptionHandler;
use PDO;
use PDOException;
use PDOStatement;

use function Gampil\App\env;
use function Gampil\App\conf;

/*
* @author Naf'an Rizkilah
* Class Database. menggunakan PDO dan juga mendukung 'Transaction'
*/
class Database {
  private PDO $db_handler;
  private PDOStatement $stmt;
  private $db_user = null, $db_pass = null;
  private $query = '';
  public function __construct() {
    $opt = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    try {
      $dsn = env('CONN_TYPE');
      // untuk mengatur dsn berdasarkan tipe koneksi
      switch($dsn) {
        case 'sqlite':
          $dsn .= ':'.conf('db_path');
          break;
        case 'mysql':
          $dsn .= ':host='.env('DB_HOST').';dbname='.env('DB_NAME');
          $this->db_user = env('DB_USER');
          $this->db_pass = env('DB_PASS');
      }
      $this->db_handler = new PDO($dsn, $this->db_user, $this->db_pass, $opt);
    } catch (PDOException $e) {
      die("Connection failed : " . $e->getMessage());
    }
  }
  // ini adalah 'Prepared Statement'
  public function query($query, $use_transaction = true) {
    $this->query = $query;
    if($use_transaction and !$this->db_handler->inTransaction()) $this->db_handler->beginTransaction();
    $this->stmt = $this->db_handler->prepare($query);
  }
  public function bind($param, $val, $type = null) {
    if (is_null($type)) {
      $type = match(true) {
        is_int($val) => PDO::PARAM_INT,
        is_bool($val) => PDO::PARAM_BOOL,
        is_null($val) => PDO::PARAM_NULL,
        default => PDO::PARAM_STR
      };
    }
    $this->stmt->bindValue($param, $val, $type);
  }
  public function run($params = [], $use_transaction = true) {
    ExceptionHandler::add_query($this->query, $params);
    foreach($params as $key => $val) $this->bind($key, $val);
    if($use_transaction === false) return $this->stmt->execute();
    $this->save(true);
  }
  // commit 'Transaction'
  public function save($exec = false) {
    try {
      if($exec) $this->stmt->execute();
      $this->db_handler->commit();
    } catch (PDOException $e) {
      $this->db_handler->rollBack();
      echo 'Failed : '.$e->getMessage();
    }
  }
  // shortcut (row_count() > 0)
  public function is_success() {
    return ($this->row_count() > 0);
  }
  // wrapper function
  public function row_count() {
    return $this->stmt->rowCount();
  }
  public function fetch_all($fetch_mode = PDO::FETCH_ASSOC) {
    return $this->stmt->fetchAll($fetch_mode);
  }
  public function fetch($fetch_mode = PDO::FETCH_ASSOC) {
    return $this->stmt->fetch($fetch_mode);
  }
  public function exec($query) {
    return $this->db_handler->exec($query);
  }

}
