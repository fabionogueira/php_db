<?php

class Driver {
    private $db = null;   //ponteiro para a conex�o atual
    public $lastSQL;
    private $lastMessage;
    protected $transCount;   //quantidade de transação aberta

    function __construct() {
        
    }
    public function connect($stringConnect, $user = "", $psw = "") {
        try {
            $this->db = new PDO($stringConnect, $user, $psw);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            exit($e->getMessage());
        }
    }
    public function errorMessage() {
        return $this->lastMessage;
    }
    public function execute($sql, $fetchRow = false, $ignoreException = false) {
        $this->lastSQL = $sql;
        
        try {
            $r = $this->db->query($sql);
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            if (!$ignoreException) {
                exit($msg);
            } else {
                $this->lastMessage = $msg;
                return null;
            }
        }

        if ($fetchRow) {
            $obj = $r->fetch(PDO::FETCH_ASSOC);
            return $obj;
        } else {
            return $r;
        }
    }
    public function validate($table, $values) {
        $fields = $this->getCollumns($table);
        $msg = '';

        foreach ($fields as $field => $attrs) {
            $type     = $attrs['vartype'];
            $nullable = $attrs['nullable'];
            $size     = $attrs['data_size'];
            $sequence = $attrs['sequence'];
            $value    = isset($values[$field]) ? $values[$field] : NULL;

            //null
            if (!$nullable && $sequence == '') {
                if (isset($values[$field]) && $value === '') {
                    $msg = "O campo <b>{$field}</b> não pode ser vazio.";
                }
            }

            if (!is_null($value)) {
                //size
                if ($type == 'text' && strlen($value) > $size) {
                    $msg = "Tamanho maior que o permitido no campo [{$field}]. [" . strlen($value) . ', ' . $size . ']';
                }
                //data type
                else {
                    switch ($type) {
                        case 'number':
                            if ($value != '' && !is_numeric($value)) {
                                $msg = "Tipo de dado inválido no campo [{$field}]. [{$value}]";
                            }
                            break;
                        case 'date':
                            if ($value != '' && !valid_date($value)) {
                                $msg = "O valor [$value] no campo [{$field}] não é uma data válida.";
                            }
                            break;
                        case 'boolean':
                            if (strtoupper($value) != '' && strtoupper($value) != 'TRUE' && strtoupper($value) != 'FALSE' && !is_bool($value) && !is_numeric($value)) {
                                $msg = "Tipo booleano inválido no campo [$field]. [" . $value . "]";
                            }
                            break;
                        case 'timestamp':
                            break;
                    }
                }
            }

            if ($msg != '') {
                return "Erro na validação de dados na tabela [$table] <br />". LF . $msg;
            }
        }

        return true;
    }

    /* self::$_tables[$table][$row['attname']] = array(
      'data_type' => $row['typname'],
      'data_size' => $row['data_len'],
      'vartype'   => pgsql::data_type_to_type($row['typname']),
      'nullable'  => $row['attnotnull']=='f',
      'key'       => $row['conname']!='' && $row['contype']=='p',
      'sequence'  => self::sequence_name($row['default_value'])
      ); */

    public function insert($table, $array, $ignoreException = false) {
        $keys1 = array();
        $keys2 = array();
        $autoField = '';

        //obtém informações sobre as colunas da tabela
        $fields = $this->getCollumns($table);

        //valida os valores de entrada
        $v = $this->validate($table, $array);
        if ($v !== true) {
            $this->lastMessage = ('INSERT ERROR: ' . $v);
            return NULL;
        }

        //prepara o comando insert
        $sql = 'INSERT INTO ' . $table . ' (';
        $s = '';
        $v = '';
        foreach ($fields as $field => $attrs) {
            $this->setSerialValue($array[$field], $attrs);

            if (isset($array[$field])) {//o campo existe no array de dados a serem inseridos
                $s = $v == '' ? '' : ',';
                $value = $this->formatValue($array[$field], $attrs['vartype']);

                //guarda as chaves primárias para serem devolvidas com seus valores
                if ($attrs['key']) {
                    $keys1[$field] = $attrs;
                }

                if ($attrs['sequence'] == 'auto_increment') {
                    $autoField = $field;
                }

                $sql .= ($s . $field);
                $v .= ($s . $value);

                $array[$field] = $value;
            }
        }
        $sql .= ') VALUES (' . $v . ');';
        
        $ss = $this->insertOrUpdateLastId();
        $r  = $this->execute($sql, false, $ignoreException);
        
        if (is_null($r)){
            return NULL;
        }
        
        $n = isset($array[$autoField]) ? $array[$autoField] : '';
        if ($ss != '' && (!isset($array[$autoField]) || is_null($n) || $n == 'null' )) {
            $r = $this->execute($ss, true);
            $array[$autoField] = $r['id'];
        }

        //prepara o retorno contendo os campos chaves e seus valores
        foreach ($keys1 as $field => $attr) {
            $keys2[$field] = $array[$field];
        }

        return $keys2;
    }
    public function update($table, $array, $cond = '', $ignoreException = false) {
        $fields = $this->getCollumns($table);

        $v = $this->validate($table, $array);
        if ($v !== true) {
            $this->lastMessage = ('UPDATE ERROR: ' . $v);
            return 0;
        }

        //monta UPDATE SET
        $sql = 'UPDATE ' . $table . ' SET ';
        $s = '';
        $where = '';

        //monta o where com as chaves primárias
        foreach ($fields as $field => $attrs) {
            if (isset($array[$field])) {//o campo existe no array de dados a serem inseridos
                $value = $this->formatValue($array[$field], $attrs['vartype']);

                //se for chave primária, coloca no where
                if (empty($cond) && $attrs['key']) {
                    $where .= ($where == '' ? '' : ' AND ') . $field . '=' . $value;
                }

                //formata o valor de acordo com o tipo de dado
                $sql .= ($s . $field . '=' . $value);
                $s = ',';

                $array[$field] = $value;
            }
        }
        $where = ' WHERE ' . $cond . ($where == '' ? '' : $cond != '' ? ' AND (' . $where . ')' : $where);
        
        $sql .= $where;
        $sqlc = 'SELECT COUNT(*) as qtd FROM ' . $table . $where . ';';
        
        $r1 = $this->to_array($this->execute($sqlc));
        $count = (int)$r1['qtd'];
        
        if ($count===0){
            $this->lastMessage = 'Record not found';
            return 0;
        }
        
        $r = $this->execute($sql, false, $ignoreException);
        
        if (is_null($r)){
            return 0;
        }
        
        return $count;
    }
    public function delete($table, $cond) {
        $sql = 'SELECT COUNT(*) as qtd FROM ' . $table . ' WHERE ' . $cond . ';';
        $sqld = 'DELETE FROM ' . $table . ' WHERE ' . $cond . ';';

        $r1 = $this->to_array($this->execute($sql));
        $this->execute($sqld);
        $r2 = $this->to_array($this->execute($sql));

        $count = ($r1['qtd'] - $r2['qtd']);

        return $count;
    }
    private function to_array($rs) {
        foreach ($rs as $row) {
            return $row;
        }
        return array();
    }

    /**
     * Retorna o sql, inserindo filter no local adequado da query
     */
    public function getSourceFilter($sql, $filter) {
        $new_sql = '';
        $where_pos = strripos($sql, 'where');

        if ($where_pos === false){
            $new_sql = $sql . ' where ' . $filter;
        }else{
            $new_sql = substr($sql, 0, $where_pos) . ' where ' . $filter . ' and ' . substr($sql, $where_pos + 4);
        }
        
        return $new_sql;
    }
    public function begin() {
        $this->db->beginTransaction();
    }
    public function commit() {
        $this->db->commit();
    }
    public function rollback() {
        $this->db->rollBack();
    }
    public function close() {
        unset($this->db);
        //$result = @pg_close($this->activeConnect);
        //return ($result===false);
    }

}
