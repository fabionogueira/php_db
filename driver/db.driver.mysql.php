<?php
require_once "db.driver.php";

class mysql extends Driver{
	private $_exception;
	
	private static $_tables=array();
	private static $_driver_types = array(
		'BIT'		=> "number",
		'TINYINT'	=> "number",
		'SMALLINT'	=> "number",
		'INT'		=> "number",
		'MEDIUMINT'	=> "number",
		'BIGINT'	=> "number",
		'FLOAT'		=> "number",
		'DOUBLE'	=> "number",
		'DECIMAL'	=> "number",
		'CHAR'		=> "text",
		'VARCHAR'	=> "text",
		'TEXT'		=> "text",
		'BINARY'	=> "text",
		'VARBINARY'	=> "text",
		'BLOB'		=> "text",
		'ENUM'		=> "text",
		'SET'		=> "text",
		'TINYTEXT'	=> "text",
		'MEDIUMTEXT'	=> "text",
		'LONGTEXT'	=> "text",
		'TINYBLOB'	=> "text",
		'MEDIUMBLOB'	=> "text",
		'LONGBLOB'	=> "text",
		'DATE'		=> "date",
		'TIME'		=> "time",
		'DATETIME'	=> "timestamp",
		'TIMESTAMP'	=> "timestamp",
		'YEAR'		=> "numeric",
		'unknown'       => "text"
	);
	
	public function connect($stringConnect, $user, $psw){
		parent::connect($stringConnect, $user, $psw);
		//parent::execute('SET datestyle TO SQL, DMY;'); //postgresql	//SET CLIENT_ENCODING TO 'UTF8';
		
		return true;
  	}
  
	public function serial($table=''){
		$rs = $this->execute("SELECT t.TABLE_NAME, t.auto_increment AS next_value
				      FROM information_schema.tables t
				      WHERE table_schema = sici' and t.TABLE_NAME=".$table."
				     "); 
		$obj = $rs->fetch(PDO::FETCH_ASSOC);
		return $obj['id'];
	}
	
	/*public function fieldValue($field, $attrs, $values){
		if ($field['sequence']!=''){
			$rs = $this->execute('SELECT last_value FROM '.$field);
			$obj = $rs->fetch(PDO::FETCH_ASSOC);
			$value = $obj['last_value'];
		}
		
		return $value;
	}*/
	
	protected function insertOrUpdateLastId(){
		return 'SELECT LAST_INSERT_ID() as id;';
	}
	
	protected function formatValue($value, $type){
		switch ($type){
			case 'text':
				str_replace("'", "\'", $value);
				$value = "'".$value."'";
				if ($value=="''"){
					$value='null';
				}
				break;
			
			case 'number':
				$value = ($value == '' ? 'null' : $value);
				break;
				
			case 'date':
				if ($value!=''){
					$value = substr($value, 6).'-'.substr($value, 3, 2).'-'.substr($value, 0, 2);
					$value = "'".$value."'";
				}else{
					$value = 'null';
				}
				break;
			
			case 'timestamp':
				if ($value!=''){
					$value = substr($value, 6).'-'.substr($value, 3, 2).'-'.substr($value, 0, 2);
					$value = "'".$value."'";
				}else{
					$value = 'null';
				}
				break;
			
			case 'boolean':
				$value = $value ? 'true' : 'false';
				break;
		}
		
		return $value;
	}
	
	protected function setSerialValue(&$fieldValue, $attrs){
		if ($attrs['sequence']=='auto_increment'){
			/*$rs = $this->execute("SELECT t.TABLE_NAME, t.auto_increment AS next_value
					      FROM information_schema.tables t
					      WHERE table_schema = 'sici' and and t.TABLE_NAME=".$table."'");
			$obj = $rs->fetch(PDO::FETCH_ASSOC);*/
			//$fieldValue = null; //$obj['id'];
		}
	}
	
	public function now(){
		return date('d/m/Y');
	}
	
	public function getCollumns($table, $schema='public'){
		if ( !isset(self::$_tables[$table]) ){
			$sql="SELECT a.EXTRA, a.table_name, a.COLUMN_NAME, a.data_type, a.character_maximum_length as data_len,
				   a.column_key, a.is_nullable
			      FROM information_schema.columns a 
			      WHERE a.table_name = '$table';";			
			
			$rs = $this->execute($sql);
			
			self::$_tables[$table] = array();			
			$exist = false;
			foreach ($rs as $row){
				$exist = true;
				self::$_tables[$table][$row['COLUMN_NAME']] = array(
					'data_type' => $row['data_type'],
					'data_size' => $row['data_len'],
					'vartype'   => mysql::data_type_to_type($row['data_type']),
					'nullable'  => $row['is_nullable']=='YES',
					'key'       => $row['column_key']=='PRI',
					'sequence'  => $row['EXTRA']
				);
			}
			
			if (!$exist)
				exit("The table $table not found!");
		}
		
		return self::$_tables[$table];
	}
	
	private static function data_type_to_type($data_type){
		$type = self::$_driver_types[ strtoupper($data_type) ];
		if ($type==''){
			$type='unknown';
		}
		return $type;
	}
}
