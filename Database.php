<?php

/**
 * PDO connection class
 * Database CRUD methods
 * @create
 * User: Ali Turki
 * Date: 20/08/17
 * Time: 04:43 PM
 */
class Database
{
    /**
     * The database connection instance.
     * @var
     */
    private static $connection;

    /**
     * Server username
     * @var string
     */
    private $host = "localhost";
    /**
     * Host username
     * @var string
     */
    private $user = "root";
    /**
     * Server password
     * @var string
     */
    private $pass = "";
    /**
     * @var string
     */
    private $dbname = "dbname";
    /**
     * @var string
     */
    private $charset = 'utf8';

    /**
     * Only if your PHP version is unacceptably outdated (namely below 5.3.6),
     * you have to use SET NAMES query and always turn emulation mode off.
     * To Learn more
     * https://phpdelusions.net/pdo#emulation
     * @var array
     */
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /* Query Builder properties*/

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    private $table = null;

    private $lastInsertedId = null;

    private $values = [];
    private $bindings = [];
    private $wheres = [];
    private $joins = [];
    private $limit;
    private $offset;
    private $orderBy = [];

    private $whereBinds = [];

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns;

    /**
     * Database constructor.
     */
    public function __construct()
    {
        // Check if is already connected
        if (!$this->isConnected())
            $this->connect();
    }


    /**
     * @return bool
     */
    private function isConnected()
    {
        return self::$connection instanceof PDO;
    }


    /**
     * Connect to Mysql Database and create an instance of PDO class
     * @return void
     */
    private function connect()
    {
        try {
            self::$connection = new PDO($this->buildDSN(), $this->user, $this->pass, $this->options);
        } catch (PDOException $e) {
            die('Connection failed: ' . $e->getMessage());
        }

    }

    /**
     * Note: that it's important to follow the proper format - no spaces or quotes or other decorations have to be used in DSN,
     * but only parameters, values and delimiters,
     * as shown in the Php manual.
     * @return string
     */
    private function buildDSN()
    {
        return "mysql:host=$this->host;dbname=$this->dbname;charset=$this->charset";
    }


    /**
     * @return mixed
     */
    private function getConnection()
    {
        return self::$connection;
    }


    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;

    }


    public function table($table)
    {
        $this->table = $table;
        return $this;

    }

    public function from($table)
    {
        $this->table = $table;
        return $this;

    }

    public function data($key, $value = null)
    {

        if (is_array($key)) {
            $this->values = array_merge($this->values, $key);
        } else {
            $this->values[$key] = $value;
        }

        return $this;

    }


    public function insert($key, $value = null)
    {

        if(!$this->table){
            throw new Exception("There's no database table selected to insert into it.");
        }

        if (is_array($key)) {
            $this->values = array_merge($this->values, $key);
        } else {
            $this->values[$key] = $value;
        }

       try{
           $sql = "INSERT INTO " . $this->table . " SET ";

           foreach ($this->values as $key => $value) {
               $sql .= '`' . $key . '`=?, ';
               $this->addToBindings($value);
           }

           $sql = rtrim($sql, ', ');

           $this->execQuery($sql, $this->bindings);
           $this->lastInsertedId = $this->getConnection()->lastInsertId();
       }catch (PDOException $e){
            return $e->getMessage();
       }

        return $this;

    }

    public function update($key, $value = null)
    {

        if(!$this->table){
            throw new Exception("There's no database table selected to insert into it.");
        }

        if (is_array($key)) {
            $this->values = array_merge($this->values, $key);
        } else {
            $this->values[$key] = $value;
        }

           $sql = "UPDATE " . $this->table . " SET ";

           foreach ($this->values as $key => $value) {
               $sql .= '`' . $key . '`=?, ';
               $this->addToBindings($value);
           }

        $sql = rtrim($sql, ', ');

        if($this->wheres){
               $sql .= " WHERE ".implode(" ",$this->wheres);
               $this->addToBindings($this->whereBinds);
           }

           $this->execQuery($sql, $this->bindings);


        return $this;

    }




    public function join($join){
        $this->joins[] = $join;

        return $this;
    }



    public function limit($limit,$offset = 0){
        $this->limit = $limit;
        $this->offset= $offset;

        return $this;
    }

    public function orderBy($orderBy,$sort = "ASC"){

        $this->orderBy = [$orderBy,$sort];

        return $this;

    }





    private function addToBindings($value)
    {

        if(is_array($value)){
            $this->bindings = array_merge($this->bindings,$value);
        }else{
            $this->bindings[] = $this->filterValue($value);
        }

    }


    public function execQuery(...$bindings)
    {

        $sql = array_shift($bindings);

        if (count($bindings) == 1 && is_array($bindings[0])) {
            $bindings = $bindings[0];
        }

        try {
            $query = $this->getConnection()->prepare($sql);

            foreach ($bindings as $k => $v) {
                $query->bindValue($k + 1, $this->filterValue($v));
            }

            $query->execute();

        } catch (PDOException $e) {
            return $e->getMessage();
        }

        $this->reset();
        return $query;

    }


    private function filterValue($value)
    {
        return htmlspecialchars($value);
    }


    public function where(...$bindings){
        $sql = array_shift($bindings);
        $this->wheres[] = $sql;
        $this->whereBinds = $bindings;
        return $this;

    }



    public function get($columns = ['*']){
        $this->columns = is_array($columns) ? $columns : func_get_args();
        $sql = $this->getStatement();
/*        pre($sql);
        pre($this->bindings);*/
        $result = $this->execQuery($sql,$this->bindings)->fetch();
        $this->reset();

        return $result;

    }


    public function getAll($columns = ['*']){
        $this->columns = is_array($columns) ? $columns : func_get_args();

        $sql = $this->getStatement();

        $result = $this->execQuery($sql,$this->bindings)->fetchAll();
        $this->reset();
        return $result;

    }


    public function delete(){

        $sql = "DELETE FROM " . $this->table;

        if($this->joins)
            $sql .= implode(" ",$this->joins);

        if($this->wheres){
            $sql .=  " WHERE ".implode(" ",$this->wheres);
            $this->addToBindings($this->whereBinds);
        }

        $query = $this->execQuery($sql);

        $this->reset();

        return $query;

    }
    private function getStatement(){

        $sql = "SELECT " . implode(",",$this->columns) . " FROM " . $this->table;

        if($this->joins)
            $sql .= implode(" ",$this->joins);

        if($this->wheres){
            $sql .=  " WHERE ".implode(" ",$this->wheres);
            $this->addToBindings($this->whereBinds);
        }

        if($this->orderBy)
            $sql .=  " ORDER BY ".implode(" ",$this->orderBy);
        if($this->limit)
            $sql .=  " LIMIT ".$this->limit;
        if($this->offset)
            $sql .=  " OFFSET ".$this->offset;

        return $sql;

    }

    private  function reset(){
        $this->table = null;
        $this->values = [];
        $this->bindings = [];
        $this->wheres = [];
        $this->joins = [];
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = [];
        $this->whereBinds = [];
    }



}