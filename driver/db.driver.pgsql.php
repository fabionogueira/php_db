<?php
require_once __DIR__."/db.driver.php";

class pgsql extends Driver{
	private $_exception;
	
	private static $_tables=array();
	private static $_driver_types = array(
		"int8"        => "number",
		"int4"        => "number",
		"int2"        => "number",
		"integer"     => "number",
		"bigint"      => "number",
		"timestamptz" => "timestamp",
		"timestamp"   => "timestamp",
		"date"        => "date",
		"time"        => "time",
		"varchar"     => "text",
		"bpchar"      => "text",
		"text"        => "text",
		"decimal"     => "number",
		"numeric"     => "number",
		"float8"      => "number",
		"float4"      => "number",
		"double precision" => "number",
		"bool"        => "boolean",
		"bytea"       => "file",
		"interval"    => "interval",
		"character_data"  => "text",
		"cardinal_number" => "number",
		"sql_identifier"  => "text",
		"name"            => "text",
		"unknown"         => "text"
	);
	
	public function connect($stringConnect, $user="", $psw=""){
		parent::connect($stringConnect, $user, $psw);
		parent::execute('SET datestyle TO SQL, DMY;'); //postgresql	//SET CLIENT_ENCODING TO 'UTF8';
		
		return true;
  	}
  
	public function serial($sequence, $table=''){
		$rs = $this->execute("select nextval('$sequence') as id"); //postgresql
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
		return '';
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
				$value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
				break;
		}
		
		return $value;
	}
	
	protected function setSerialValue(&$field, $attrs){
		if ($attrs['sequence']!=''){
			$rs = $this->execute("select nextval('".$attrs['sequence']."') as id");
			$obj = $rs->fetch(PDO::FETCH_ASSOC);
			$field = $obj['id'];
		}
	}
	
	public function now(){
		return date('d/m/Y');
	}
	
	public function getCollumns($table, $schema='public'){
		if ( !isset(self::$_tables[$table]) ){
			$sql="SELECT a.attname, a.attnotnull, a.atthasdef, t.typname, d.adsrc AS default_value, r.contype, r.conname, CASE a.atttypmod WHEN -1 THEN -1 ELSE a.atttypmod-4 END AS data_len
					FROM pg_attribute a 
				   INNER JOIN pg_class c ON (c.oid = a.attrelid AND c.relkind = 'r')
				   INNER JOIN pg_type t ON (t.oid = a.atttypid AND t.typname NOT IN ('oid', 'tid', 'xid', 'cid')) 
				   LEFT JOIN pg_attrdef d ON (d.adrelid = a.attrelid AND d.adnum = a.attnum) 
				   LEFT JOIN pg_constraint r ON (r.conrelid = a.attrelid AND (r.conkey[1] = a.attnum
					  OR r.conkey[2] = a.attnum OR r.conkey[3] = a.attnum OR r.conkey[4] = a.attnum
					  OR r.conkey[5] = a.attnum OR r.conkey[6] = a.attnum) OR r.conkey[7] = a.attnum
					  OR r.conkey[8] = a.attnum)
				   WHERE c.relname = '$table';";			
			
			$rs = $this->execute($sql);
			$fields = array();
                        
			self::$_tables[$table] = array();			
			$exist = false;
			foreach ($rs as $key=>$row){
                                $exist = true;
                                $r = array(
                                        'data_type' => $row['typname'],
                                        'data_size' => $row['data_len'],
                                        'vartype'   => pgsql::data_type_to_type($row['typname']),
                                        'nullable'  => $row['attnotnull']=='f' ? false : true,
                                        'key'       => ($row['conname']!='' && $row['contype']=='p') ? true : false,
                                        'sequence'  => self::sequence_name($row['default_value'])
                                );

                                if (!isset($fields[$row['attname']])){
                                    $fields[$row['attname']] = $r;
                                }
			}
			
                        self::$_tables[$table] = $fields;
                        
			if (!$exist) {
				exit("The table $table not found!");
                        }
		}
		
		return self::$_tables[$table];
	}
	
	private static function data_type_to_type($data_type){
		$type = self::$_driver_types[$data_type];
		if ($type==''){
			$type='unknown';
		}
		return $type;
	}
	
	private static function sequence_name($data_default){
		$r = '';
		$a1 = explode('::', $data_default);
		if (count($a1)==2){
			$a2 = explode('(', $a1[0]);
			if ($a2[0]=='nextval'){
				$r = str_replace("'", '', $a2[1]);
			}
		}
		return $r;
	}
	
}
