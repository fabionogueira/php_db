<?php

require_once __DIR__.'/Recordset.php';

class DB {
    /**
     * @var Recordset
     */
    private $_rs;
    private $_config = array();
    private $_error = NULL;
    
    public static $FETCH_ROW = 'row';
    public static $FETCH_TABLE = 'table';
    
    function __construct($config=array()) {
        $this->_config = $config;
    }
    
    private function init() {
        if (!isset($this->_rs)) {
            $this->_rs = new Recordset($this->_config);
        }
    }
    
    public function config($config){
        $this->_config = $config;
    }
    public function getError(){
        return $this->_error;
    }
    public function insert($table, $fields, $ignoreException = false) {
        $this->init();
        $this->_error = NULL;
        
        $r = $this->_rs->insert($table, $fields, $ignoreException);
        
        if (is_null($r)){
            $this->_error = $this->_rs->errorMessage();
        }
        
        return $r;
    }
    public function update($table, $fields, $where = '', $ignoreException = false) {
        $this->init();
        $this->_error = NULL;
        
        $r = $this->_rs->update($table, $fields, $where, $ignoreException);
        
        if ($r==0){
            $this->_error = $this->_rs->errorMessage();
            if (is_null($this->_error) ){
                $this->_error='Undefined Error.';
            }
        }
        
        return $r;
    }
    public function delete($table, $where) {
        $this->init();
        return $this->_rs->delete($table, $where);
    }
    public function execute($sql, $fetch=NULL) {
        $this->init();
        
        if (is_null($fetch)) {
            return $this->_rs->execute($sql);
        } else if ($fetch==DB::$FETCH_ROW) {
            return $this->_rs->execute($sql, true);
        } else if ($fetch==DB::$FETCH_TABLE) {
            $r = $this->_rs->execute($sql);
            return self::resultToTable($r);
        } else {
            return $this->_rs->execute($sql);
        }
    }
    public function begin() {
        $this->init();
        return $this->_rs->begin();
    }
    public function commit() {
        $this->init();
        return $this->_rs->commit();
    }
    public function rollback() {
        $this->init();
        return $this->_rs->rollback();
    }
    public function resultToTable($result) {
        $arr = array();

        foreach ($result as $row) {
            $r = array();

            foreach ($row as $key => $value) {
                if (!is_numeric($key)) {
                    $r[$key] = $value;
                }
            }

            array_push($arr, $r);
        }

        return $arr;
    }
}

class DBStatic{
    private static $db;
    
    public static $FETCH_ROW = 'row';
    public static $FETCH_TABLE = 'table';
    
    public static function config($config){
        self::$db = new DB($config);
    }
    public static function getError(){
        return self::$db->getError();        
    }
    public static function insert($table, $fields, $ignoreException = false){
        return self::$db->insert($table, $fields, $ignoreException);
    }
    public static function update($table, $fields, $where = '', $ignoreException = false){
        return self::$db->update($table, $fields, $where, $ignoreException);
    }
    public static function delete($table, $where){
        return self::$db->delete($table, $where);
    }
    public static function execute($sql, $fetch=NULL){
        return self::$db->execute($sql, $fetch);
    }
    public static function begin(){
        return self::$db->begin();
    }
    public static function commit(){
        return self::$db->commit();
    }
    public static function rollback(){
        return self::$db->rollback();
    }
    public static function resultToTable($result){
        return self::$db->resultToTable($result);
    }
}