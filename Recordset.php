<?php
class Recordset {
    /**
     * @var Driver
     **/
    private $_driver;

    function __construct($config) {
        $driver      = $config['driver'];
        $str_connect = "{$driver}:host={$config['host']};dbname={$config['dbname']}";
        $user        = $config['user'];
        $psw         = $config['password'];

        $file = __DIR__."/driver/db.driver.{$driver}.php";
        require_once $file;

        $this->_driver = new $driver(); //instancia o driver do banco e dados específico
        $this->_driver->connect($str_connect, $user, $psw); //conecta-se ao banco de dados
    }

    function __destruct(){

    }

    public function close(){

    }

    public function errorMessage(){
        return $this->_driver->errorMessage();
    }

    /** 
     * @return pgsql
     */
    private static function instance(){
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function execute($sql, $fetchRow=false){
        return $this->_driver->execute($sql,$fetchRow);
    }

    public function insert($table, $fields, $ignoreException=false){
        //if ( !isset($fields['dt_insert']) ) $fields['dt_insert'] = $this->_driver->now();
        //if ( !isset($fields['no_insert']) ) $fields['no_insert'] = Session::userName();

        return $this->_driver->insert($table, $fields, $ignoreException);
    }

    public function update($table, $array, $cond='', $ignoreException=false){
        //if ( !isset($array['dt_update']) ) $array['dt_update'] = $this->_driver->now();
        //if ( !isset($array['no_update']) ) $array['no_update'] = Session::userName();

        return $this->_driver->update($table, $array, $cond, $ignoreException);
    }

    public function delete($table, $cond){
        return $this->_driver->delete($table, $cond);
    }

    public function begin(){
        $this->_driver->begin();
    }

    public function commit(){
        $this->_driver->commit();
    }

    public function rollback(){
        $this->_driver->rollback();
    }
	
}
