<?php
namespace Gampil\App;


use Gampil\Database\Database;
use Gampil\App\Container;

/*
 * @author Naf'an Rizkilah
 * Class dasar untuk semua model. dilengkapi query builder, relationship dan factory.
*/
class Model {
  private ?Database $db;
  private $table = '';
  private $select = '';// menyimpan 'column' untuk diambil
  private $where = [];// menyimpan data untuk kondisi 'WHERE' ([column, $operator, $value])
  private $order_by = [];
  private $limit;
  private $joins = [];// menyimpan table-table untuk 'JOIN'(khsusunya pada saat eager loading)
  private $relationships = [];
  private $conn_type = '';// menentukan jenis database untuk menentukan query
  private $data = [];// data tunggal ['col1' => 'val1']
  private $datas = [];// array data [0 => ['col1' => 'val1']]
  private $columns = [];// 'columns' dari table di model ini
  private $clear_later = false;// untuk menunda 'clear_query'
  private $use_transaction = true;
  public $data_idx = -1;// index untuk mendapatkan data di $datas
  public function __construct() {
    $this->db = Container::get('db');
    if(empty($this->table)) $this->table = str_replace('models\\', '', static::class);
    $this->conn_type = env('CONN_TYPE');
    // mendapatkan columns dari 'Container' jika ada, kalau tidak ada dapatkan dari get_columns()
    $this->columns = (Container::has($this->table.'_cols')) ? Container::get($this->table.'_cols') : $this->get_columns();
    $this->init();
  }
  // getter otomatis untuk mengakses properti model (misal, user->id, user->profile)
  // @return data dari hasil 'SELECT' atau 'relationship' atau null
  public function __get($property) {
    if(isset($this->data[$property])) return $this->data[$property];
    if(isset($this->relationships[$property])) return $this->load_relationship($property);// Lazy Loading
    return null;
  }
  // untuk menambahkan $this->data_idx dan mendapatkan data dari $this->datas[$this->data_idx]
  public function __clone() {
    if(empty($this->datas)) return;
    $this->data_idx++;
    if($this->data_idx >= count($this->datas)) $this->data_idx = $this->data_idx - 1;
    $this->data = $this->datas[$this->data_idx];
  }
  // dibutuhkan untuk 'Eager Loading'
  public function set_data($data) {$this->data = $data;}
  // dibutuhkan untuk 'Eager Loading'
  public function add_data($data) {$this->data += $data;}
  // untuk tempat mendefinisikan 'relationship'
  protected function init() {}
  // untuk membuat array agar berawalan index '1'. dibutuhkan untuk query dengan kondisi 'WHERE'
  private function move_indexes($arr) {
    $new_arr = [];
    if(count(array_filter(array_keys($arr), 'is_string')) > 0) {
      $i = 0;
      foreach($arr as $key => $val) {
        $new_arr[$i + 1] = $val;
        $i += 1;
      }
      return $new_arr;
    }
    foreach($arr as $key => $val) {
      $new_arr[$key + 1] = $val;
    }
    return $new_arr;
  }
  // mendapatkan bindings valid yang diperlukan untuk kondisi 'WHERE'
  private function get_bindings() {
    $bindings = [];
    foreach($this->where as $cond) $bindings[] = $cond[2];
    $bindings = $this->move_indexes($bindings);
    return $bindings;
  }
  // menggabungkan $datas + $this->get_bindings() dan membungkusnya dengan move_indexes(). dibutuhkan untuk query 'UPDATE'
  private function merge_bindings($datas) {
    $bindings = [];
    foreach($datas as $val) {$bindings[] = $val;}
    foreach($this->get_bindings() as $val) {$bindings[] = $val;}
    $bindings = $this->move_indexes($bindings);
    return $bindings;
  }
  // dibutuhkan untuk load_relationship()
  public function get_table() {
    return $this->table;
  }
  private function get_columns() {
    if(!empty($this->columns)) return $this->columns;
    $query = match(true) {
      $this->conn_type === 'sqlite' => "PRAGMA table_info('{$this->table}')",
      $this->conn_type === 'mysql' => "SHOW COLUMNS FROM {$this->table}"
    };
    $this->db->query($query);
    $this->db->run();
    $columns = $this->db->fetch_all();
    $columns = match(true) {
      $this->conn_type === 'sqlite' => array_column($columns, 'name'),
      $this->conn_type === 'mysql' => array_column($columns, 'Field')
    };
    // menambahkan $columns ke Container, supaya tidak perlu query lagi
    Container::add($this->table.'_cols', $columns);
    return $columns;
  }
  # Query Builder. :109 - :252
  public function all() {return $this->select()->get(false);}
  public function select($cols = '*') {
    if(is_array($cols)) {
      // supaya terhindar dari 'ambiguous column name'
      $prefixed_cols = array_map(function($col) {return (strpos($col, '.') === false) ? $this->table.'.'.$col : $col; }, $cols);
      $this->select .= implode(', ', $prefixed_cols).', ';
    } else $this->select .= ($cols === '*') ? $this->table.'.'.$cols.', ' : $cols.', ';// supaya terhindar dari 'ambiguous column name'
    return $this;
  }
  public function where($column, $value, $operator = '=') {
    $this->where[] = [$column, $operator, $value];
    return $this;
  }
  // mencari id
  public function id($id) {return $this->select()->where($this->table.'.id', $id);}
  public function order_by($column, $direction = 'ASC') {
    $this->order_by[] = [$column, $direction];
    return $this;
  }
  public function limit(int $limit, $offset = null) {
    $this->limit = (!is_int($offset)) ? $limit : [$limit, $offset];
    return $this;
  }
  public function first() {
    if(empty($this->datas)) $this->select()->get();// mengambil data-data untuk operasi berikutnya jika '$this->datas' kosong 
    $first_key = array_keys($this->data)[0];
    return (is_integer($first_key)) ? $this->data[$first_key] : $this->data;
  }
  public function newest($column = 'created_at') {
    return $this->order_by($column, 'DESC');
  }
  public function oldest($column = 'created_at') {
    return $this->order_by($column);
  }
  // menambahkan Model(table) supaya dimuat dengan 'Eager Loading'
  public function with($relations) {
    if(is_string($relations)) {
      $relations = str_replace(' ', '', $relations);
      $relations = str_replace("\r", '', $relations);
      $relations = str_replace("\n", '', $relations);
      $relations = explode(',', $relations);
    }
    $this->joins = $relations;
    return $this;
  }
  // untuk mengambil data, jika $this->joins tidak kosong, lakukan 'Eager Loading'
  // @return array[Model] jika $single = false. '$this' jika $single = true
  public function get($single = true) {// fix eager load
    if(!empty($this->joins)) return $this->eager_load($single);// Eager Loading
    $query = "SELECT ".rtrim($this->select, ', ')." FROM ".$this->table;
    $query = $this->parse_condition($query);
    $this->db->query($query);
    $this->db->run($this->get_bindings());
    $results = ($single) ? $this->db->fetch() : $this->db->fetch_all();
    if($results === false) return [];// untuk mengatasi 'row' yang tidak ditemukan
    $this->data = $results;
    $first_key = array_keys($results)[0];
    if(is_string($first_key)) {
      // jika memakai fetch
      $this->clear_query();
      return $this;
    }
    // jika memakai fetch_all
    $objs = $this->clone_objs($results);
    $this->clear_query();
    return $objs;
  }
  public function get_all() {$this->get(false);}
  public function insert($data) {
    $date = date("Y-m-d H:i:s");
    if(is_int(array_search('created_at', $this->columns)) and is_int(array_search('updated_at', $this->columns))) {
      $data['created_at'] = $data['created_at'] ?? $date;
      $data['updated_at'] = $data['updated_at'] ?? $date;
    }
    var_dump(array_search('created_at', $this->columns));
    $cols = implode(', ', array_keys($data));
    $vals = rtrim(str_repeat('?, ', count($data)), ', ');
    $query = "INSERT INTO ".$this->table." (".$cols.") VALUES (".$vals.")";
    $this->db->query($query);
    $this->db->run($this->move_indexes($data), $this->use_transaction);
    $this->clear_query();
    return $this->db->row_count();
  }
  public function update($data, $force = false) {
    $set = '';
    if(isset($this->columns['updated_at'])) $data['updated_at'] = $data['updated_at'] ?? date("Y-m-d H:i:s");
    foreach($data as $col => $val) $set .= $col.' = ?,';
    $set = rtrim($set, ', ');
    $query = "UPDATE ".$this->table.' SET '.$set;
    if(empty($this->where) and isset($this->data['id'])) $this->where('id', $this->id);
    if(!empty($this->where)) {
      $query .= " WHERE ";
      foreach($this->where as $key => $cond) {
        $query .= $key > 0 ? ' AND ' : '';
        $query .= $cond[0]." ".$cond[1]." ?";
      }
    }
    if(empty($this->where) and !$force) trigger_error("there's no 'WHERE' statement and \$force option is false, i save you from accidentally change all datas.", E_USER_WARNING);
    $this->db->query($query);
    $this->db->run($this->merge_bindings($data));
    $this->clear_query();
    return $this->db->row_count();
  }
  public function delete($soft_delete = false, $force = false) {
    if(empty($this->where) and isset($this->data['id'])) $this->where('id', $this->id);
    if($soft_delete) return $this->update(['deleted_at' => date("Y-m-d H:i:s")]);
    $query = 'DELETE FROM '.$this->table;
    if(!empty($this->where)) {
      $query .= " WHERE ";
      foreach($this->where as $key => $cond) {
        $query .= $key > 0 ? ' AND ' : '';
        $query .= $cond[0]." ".$cond[1]." ?";
      }
    }
    if(empty($this->where) and !$force) trigger_error("there's no 'WHERE' statement and \$force option is false , i save you from accidentally delete all datas.", E_USER_WARNING);
    $this->db->query($query);
    $this->db->run($this->get_bindings());
    $this->clear_query();
    return $this->db->row_count();
  }
  public function count() {
    $query = "SELECT COUNT(*) as count FROM {$this->table}";
    if(!empty($this->where)) {
      foreach($this->where as $key => $cond) {
      $query .= " WHERE ";
        $query .= $key > 0 ? ' AND ' : '';
        $query .= $cond[0]." ".$cond[1]." ?";
      }
    }
    $this->db->query($query);
    $this->db->run($this->get_bindings());
    if(!$this->clear_later) $this->clear_query();
    $res = $this->db->fetch();
    return $res['count'];
  }
  // mengolah data untuk proses 'pagination'. opsi $type adalah 'full', 'short', 'number'
  // @return [array[Model], array[string]]
  public function paginate($per_page, $type = 'full') {
    $curr_page = (isset($_GET['page'])) ? ((int) $_GET['page']) : 1;
    $offset = ($curr_page - 1) * $per_page;
    $this->clear_later = true;
    $total = $this->count();// jika tidak memakai 'clear_later', 'Eager Loading' TIDAK AKAN PERNAH dilakukan
    $this->clear_later = false;
    $bindings = $this->get_bindings();
    $this->limit($per_page, $offset);
    $links = $this->gen_links($curr_page, $per_page, $total, $type);
    if(!empty($this->joins)) {
      $objs = $this->eager_load(false, $bindings);
      $this->clear_query();
      return [$objs, $links];
    }
    $query = "SELECT ".rtrim($this->select, ', ')." FROM ".$this->table;
    $query = $this->parse_condition($query);
    $this->db->query($query);
    $this->db->run($bindings);
    $results = $this->db->fetch_all();
    $objs = $this->clone_objs($results);
    return [$objs, $links];
  }
  // 'Relationship'.
  public function has_one($model, $foreign_key, $local_key = 'id') {
    $short_name = str_replace("models\\", '', $model);
    $this->relationships[strtolower($short_name)] = [
      'type' => 'one',
      'model' => $model,
      'foreign_key' =>$foreign_key,
      'local_key' => $local_key
    ];
  }
  public function has_many($model, $foreign_key, $local_key = 'id') {
    $short_name = str_replace("models\\", '', $model);
    $this->relationships[strtolower($short_name)] = [
      'type' => '',
      'model' => $model,
      'foreign_key' => $foreign_key,
      'local_key' => $local_key
    ];
  }
  public function belongs_to_many($model, $pivot_table, $foreign_key, $related_key, $local_key = 'id') {
    $short_name = str_replace("models\\", '', $model);
    $this->relationships[strtolower($short_name)] = [
      'type' => 'many',
      'model' => $model,
      'pivot_table' => $pivot_table,
      'foreign_key' => $foreign_key,
      'related_key' => $related_key,
      'local_key' => $local_key
    ];
  }
  // dibutuhkan untuk 'Lazy Loading'
  // @return Model|null
  private function load_relationship($relationship) {
    $relation = $this->relationships[$relationship];
    $related_model = new $relation['model'];
    $related_data = null;
    if($relation['type'] === 'one') {
      $foreign_key = $relation['foreign_key'];
      $local_key = $relation['local_key'];
      $related_data = $related_model->where($foreign_key, $this->data[$local_key])->first();
    } elseif($relation['type'] === 'many') {
      $pivot_table = $relation['pivot_table'];
      $foreign_key = $relation['foreign_key'];
      $related_key = $relation['related_key'];
      $local_key = $relation['local_key'];# TODO : INI SESAT TOLONG GANTI QUERYNYA !
      $query = "SELECT {$related_model->get_table()}.* FROM {$related_model->get_table()} INNER JOIN $pivot_table.$related_key = $pivot_table.$related_key WHERE $pivot_table.$foreign_key = ?";
      $this->db->query($query);
      $this->db->run([1 => $this->data[$local_key]]);
      $related_data = $this->db->fetch_all();
    } else {
      $foreign_key = $relation['foreign_key'];
      $local_key = $relation['local_key'];
      $related_data = $related_model->where($foreign_key, $this->data[$local_key])->get();
    }
    if(empty($related_data)) return null;
    $this->data[$relationship] = $related_data;
    return $related_data;
  }
  private function eager_load($single = false, $bindings = []) {
    $query = "SELECT {$this->table}.*, ";
    foreach($this->joins as $join) {
      $relation = $this->relationships[$join];
      $related_model = new $relation['model'];
      $related_columns = $related_model->get_columns();
      $related_table = $related_model->get_table();
      $related_columns = array_map(function($col) use ($related_table) {
        return "$related_table.$col AS _".$related_table."_".$col;
      }, $related_columns);
      $query .= implode(', ', $related_columns).", ";
    }
    $query = rtrim($query, ', ')." FROM {$this->table} ";
    foreach($this->joins as $join) {
      $relation = $this->relationships[$join];
      $related_model = new $relation['model'];
      $related_table = $related_model->get_table();
      if($relation['type'] === 'one' or $relation['type'] === '') {
        $local_key = $relation['local_key'];
        $foreign_key = $relation['foreign_key'];
        $query .= "LEFT JOIN $related_table ON $related_table.$foreign_key = {$this->table}.$local_key ";
      } else {
        $local_key = $relation['local_key'];
        $foreign_key = $relation['foreign_key'];
        $pivot_table = $relation['pivot_table'];
        $related_key = $relation['related_key'];
        $query .= "LEFT JOIN $pivot_table ON {$this->table}.$local_key = $pivot_table.$related_key ";
        $query .= "LEFT JOIN $related_table ON $related_table.$foreign_key = {$this->table}.$local_key";
      }
    }
    $query = $this->parse_condition($query);
    $this->db->query($query);
    $this->db->run($bindings);
    $results = ($single) ? $this->db->fetch() : $this->db->fetch_all();
    $this->clear_query();
    if($results === false) return null;
    $first = array_keys($results)[0];
    if(is_int($first)) {
      $objs = [];
      foreach($results as $res) {
        $model_datas = [];
        $datas = [];
        foreach($res as $col => $val) {
          if(!str_starts_with($col, '_')) {
            $datas[$col] = $val;
            continue;
          }
          $parts = explode('_', $col, 3);
          array_shift($parts);
          $model_datas[$parts[0]][$parts[1]] = $val;
        }
        foreach($model_datas as $table => $columns) {
          $model = new $this->relationships[$table]['model'];
          $model->set_data($columns);
          $obj = new $this;
          $datas[$table] = $model;
          $obj->set_data($datas);
        }
        $objs[] = $obj;
      }
      return $objs;
    }
    $model_datas = [];
    $datas = [];
    foreach($results as $col => $val) {
      if(!str_starts_with($col, '_')) {
        $datas[$col] = $val;
        continue;
      }
      $parts = explode('_', $col);
      array_shift($parts);
      $model_datas[$parts[0]][$parts[1]] = $val;
    }
    $this->set_data($datas);
    foreach($model_datas as $table => $columns) {
      $model = new $this->relationships[$table]['model'];
      $model->set_data($columns);
      $this->add_data([$table => $model]);
    }
    return $this;
  }
  // membersihkan data query sebelumnya supaya tidak terjadi error
  public function clear_query() {
    $this->limit = null;
    $this->order_by = [];
    $this->select = '';
    $this->where = [];
    $this->joins = [];
  }
  // untuk men'generate' navigasi halaman untuk 'pagination'.
  private function gen_links($curr_page, $per_page, $total, $type) {
    $total_pages = ceil($total / $per_page);
    $links = '';
    if($type != 'number') {
      if($curr_page > 1) $links .= str_replace('{page}', ($curr_page - 1), conf('page-before'));
    }
    for($i = 1; $i <= $total_pages; $i++) {
      if($i == $curr_page) {
        $links .= str_replace('{page}', $i, conf('page-active'));
        continue;
      }
      if($type == 'short') continue;
      $links .= str_replace('{page}', $i, conf('page-normal'));
    }
    if($type != 'number') {
      if($curr_page < $total_pages) $links .= str_replace('{page}', ($curr_page + 1), conf('page-next'));
    }
    return $links;
  }
  public function clear_datas() {$this->datas = null;}
  // untuk mengkloning Model dalam kasus dimana hasil 'SELECT' adalah [0 => ['column' => 'val1']].
  // set $this->datas = $results, lalu kloning object sebanyak count($results). setiap kloning $obj akan
  // menjalankan __clone() yang dijelaskan sebelumnya.
  // @return array[Model]
  private function clone_objs($results) {
    $this->datas = $results;
    $objs = [];
    $obj = $this;
    for($i = 0; $i < count($results); $i++) {
      $obj = clone $obj;
      $objs[$i] = $obj;
    }
    // di bawah ini untuk membersihkan $this->datas
    for($i = 0; $i < count($this->datas); $i++) $objs[$i]->clear_datas();
    if(empty($this->joins)) return $objs;
  }
  public function run_factory($data_count = 1) {
    $faker = \Faker\Factory::create(conf('faker_locale'));
    $this->use_transaction = false;
    for($i = 0; $i < $data_count; $i++) $this->factory($faker, $i);
    $this->use_transaction = true;
    $this->db->save(false);
  }
  protected function factory(\Faker\Generator $faker, $i) {}
  public function get_result_array() {
    $result = [];
    $cols = array_combine($this->columns, $this->columns);
    foreach($this->data as $col => $val) {
      if(!isset($cols[$col])) {
        $result[$col] = $this->data[$col]->get_result_array();
        continue;
      }
      $result[$col] = $val;
      // untuk mengambil data array dari 'relationship'
    }
    return $result;
  }
  // mengolah query dengan kondisi 'WHERE', 'ORDER BY', 'LIMIT'
  private function parse_condition($query) {
    if(!empty($this->where)) {
      $query .= " WHERE ";
      foreach($this->where as $key => $cond) {
        $query .= $key > 0 ? ' AND ' : '';
        $query .= "{$cond[0]} {$cond[1]} ?";
      }
    }
    if(!empty($this->order_by)) {
      $query .= ' ORDER BY ';
      foreach($this->order_by as $key => $order) {
        $query .= $key > 0 ? ', ' : '';
        $query .= "{$order[0]} {$order[1]}";
      }
    }
    if(!empty($this->limit)) {
      $query .= (is_array($this->limit)) ? " LIMIT {$this->limit[0]} OFFSET {$this->limit[1]}" : "LIMIT {$this->limit}";
    }
    return $query;
  }
}
