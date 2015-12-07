<?php
class MySQL {
	
	// Base variables
    public  $lastError;         // Holds the last error
	public  $lastQuery;         // Holds the last query
	public  $result;            // Holds the MySQL query result
	public  $records;           // Holds the total number of records returned
	public  $affected;          // Holds the total number of records affected
	public  $rawResults;        // Holds raw 'arrayed' results
	public  $arrayedResult;     // Holds an array of the result
	
	private $hostname;          // MySQL Hostname
	private $username;          // MySQL Username
	private $password;          // MySQL Password
	private $database;          // MySQL Database
	
	private $databaseLink;      // Database Connection Link
	
	/* *******************
	 * Construtor das Classes *
	 * *******************/
	
	function __construct($database, $username, $password, $hostname='localhost', $port=3306, $persistant = false){
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		$this->hostname = $hostname.':'.$port;
		
		$this->Connect($persistant);
	}
	
	/* *******************
	 * Destruidor das classes *
	 * *******************/
	
	function __destruct(){
		$this->closeConnection();
	}	
	
	/* *******************
	 * Funções Privadas *
	 * *******************/
	
	// Liga as classes à base de dados
	// $persistant (boolean) - Use persistant connection?
	private function Connect($persistant = false){
		$this->CloseConnection();
		
		if($persistant){
			$this->databaseLink = mysql_pconnect($this->hostname, $this->username, $this->password);
		}else{
			$this->databaseLink = mysql_connect($this->hostname, $this->username, $this->password);
		}
		
		if(!$this->databaseLink){
   		$this->lastError = 'Não foi possível ligar ao servidor: ' . mysql_error($this->databaseLink);
			return false;
		}
		
		if(!$this->UseDB()){
			$this->lastError = 'Não foi possível ligar à base de dados: ' . mysql_error($this->databaseLink);
			return false;
		}
		
		$this->setCharset(); // TODO: remover charset para encontrar uma gestão específica
		return true;
	}
	
	
	// Selecionar base de dados a usar
	private function UseDB(){
		if(!mysql_select_db($this->database, $this->databaseLink)){
			$this->lastError = 'Não é possível selecionar a base de dados: ' . mysql_error($this->databaseLink);
			return false;
		}else{
			return true;
		}
	}
	
	
	// Executa um 'mysql_real_escape_string' na array/string inteira
	private function SecureData($data, $types=array()){
		if(is_array($data)){
            $i = 0;
			foreach($data as $key=>$val){
				if(!is_array($data[$key])){
                    $data[$key] = $this->CleanData($data[$key], $types[$i]);
					$data[$key] = mysql_real_escape_string($data[$key], $this->databaseLink);
                    $i++;
				}
			}
		}else{
            $data = $this->CleanData($data, $types);
			$data = mysql_real_escape_string($data, $this->databaseLink);
		}
		return $data;
	}
    
    // limpa a variável com os tipos dados
    // possíveis tipos, str, int, float, bool, datetime, ts2dt (given timestamp convert to mysql datetime)
    // bonus types: hexcolor, email
    private function CleanData($data, $type = ''){
        switch($type) {
            case 'none':
				// useless do not reaffect just do nothing
                //$data = $data;
                break;
            case 'str':
            case 'string':
                settype( $data, 'string');
                break;
            case 'int':
            case 'integer':
                settype( $data, 'integer');
                break;
            case 'float':
                settype( $data, 'float');
                break;
            case 'bool':
            case 'boolean':
                settype( $data, 'boolean');
                break;
            // Y-m-d H:i:s
            // 2015-01-01 12:30:30
            case 'datetime':
                $data = trim( $data );
                $data = preg_replace('/[^\d\-: ]/i', '', $data);
                preg_match( '/^([\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:[\d]{2}:[\d]{2})$/', $data, $matches );
                $data = $matches[1];
                break;
            case 'ts2dt':
                settype( $data, 'integer');
                $data = date('Y-m-d H:i:s', $data);
                break;
            // bonus types
            case 'hexcolor':
                preg_match( '/(#[0-9abcdef]{6})/i', $data, $matches );
                $data = $matches[1];
                break;
            case 'email':
                $data = filter_var($data, FILTER_VALIDATE_EMAIL);
                break;
            default:
                break;
        }
        return $data;
    }
    /* ******************
     * Funções Públicas *
     * ******************/
    // Executa MySQL Query
    public function executeSQL($query){
        $this->lastQuery = $query;
        if($this->result = mysql_query($query, $this->databaseLink)){
            if (gettype($this->result) === 'resource') {
                $this->records  = @mysql_num_rows($this->result);
            } else {
               $this->records  = 0;
            }
            $this->affected = @mysql_affected_rows($this->databaseLink);
            if($this->records > 0){
                $this->arrayResults();
                return $this->arrayedResult;
            }else{
                return true;
            }
        }else{
            $this->lastError = mysql_error($this->databaseLink);
            return false;
        }
    }
	public function commit(){
		return mysql_query("COMMIT", $this->databaseLink);
	}
  
	public function rollback(){
		return mysql_query("ROLLBACK", $this->databaseLink);
	}
	public function setCharset( $charset = 'UTF8' ) {
		return mysql_set_charset ( $this->SecureData($charset,'string'), $this->databaseLink);
	}
	
    // Adds a record to the database based on the array key names
    public function insert($table, $vars, $exclude = '', $datatypes=array()){
        // Catch Excepções
        if($exclude == ''){
            $exclude = array();
        }
        array_push($exclude, 'MAX_FILE_SIZE'); // Automaticamente excluir esta
        // Preparar variáveis
        $vars = $this->SecureData($vars, $datatypes);
        $query = "INSERT INTO `{$table}` SET ";
        foreach($vars as $key=>$value){
            if(in_array($key, $exclude)){
                continue;
            }
            $query .= "`{$key}` = '{$value}', ";
        }
        $query = trim($query, ', ');
        return $this->executeSQL($query);
    }
    // Deletes a record from the database
    public function delete($table, $where='', $limit='', $like=false, $wheretypes=array()){
        $query = "DELETE FROM `{$table}` WHERE ";
        if(is_array($where) && $where != ''){
            // Preparar varíáveis
            $where = $this->SecureData($where, $wheretypes);
            foreach($where as $key=>$value){
                if($like){
                    $query .= "`{$key}` LIKE '%{$value}%' AND ";
                }else{
                    $query .= "`{$key}` = '{$value}' AND ";
                }
            }
            $query = substr($query, 0, -5);
        }
        if($limit != ''){
            $query .= ' LIMIT ' . $limit;
        }
        return $this->executeSQL($query);
    }
    // Gets a single row from $from where $where is true
    public function select($from, $where='', $orderBy='', $limit='', $like=false, $operand='AND',$cols='*', $wheretypes=array()){
        // Catch Excepções
        if(trim($from) == ''){
            return false;
        }
        $query = "SELECT {$cols} FROM `{$from}` WHERE ";
        if(is_array($where) && $where != ''){
            // Preparar variáveis
            $where = $this->SecureData($where, $wheretypes);
            foreach($where as $key=>$value){
                if($like){
                    $query .= "`{$key}` LIKE '%{$value}%' {$operand} ";
                }else{
                    $query .= "`{$key}` = '{$value}' {$operand} ";
                }
            }
            $query = substr($query, 0, -(strlen($operand)+2));
        }else{
            $query = substr($query, 0, -6);
        }
        if($orderBy != ''){
            $query .= ' ORDER BY ' . $orderBy;
        }
        if($limit != ''){
            $query .= ' LIMIT ' . $limit;
        }
        $result = $this->executeSQL($query);
        if(is_array($result)) return $result;
        return array();
    }
    // Updates a record in the database based on WHERE
    public function update($table, $set, $where, $exclude = '', $datatypes=array(), $wheretypes=array()){
        // catch excepções
        if(trim($table) == '' || !is_array($set) || !is_array($where)){
            return false;
        }
        if($exclude == ''){
            $exclude = array();
        }
        array_push($exclude, 'MAX_FILE_SIZE'); // Automatically exclude this one
        $set 	= $this->SecureData($set, $datatypes);
        $where 	= $this->SecureData($where,$wheretypes);
        // SET
        $query = "UPDATE `{$table}` SET ";
        foreach($set as $key=>$value){
            if(in_array($key, $exclude)){
                continue;
            }
            $query .= "`{$key}` = '{$value}', ";
        }
        $query = substr($query, 0, -2);
        // WHERE
        $query .= ' WHERE ';
        foreach($where as $key=>$value){
            $query .= "`{$key}` = '{$value}' AND ";
        }
        $query = substr($query, 0, -5);
        return $this->executeSQL($query);
    }
    // 'Arrays' um resultado único
    public function arrayResult(){
        $this->arrayedResult = mysql_fetch_assoc($this->result) or die (mysql_error($this->databaseLink));
        return $this->arrayedResult;
    }
    // 'Arrays' multiplos resultados
    public function arrayResults(){
        if($this->records == 1){
            return $this->arrayResult();
        }
        $this->arrayedResult = array();
        while ($data = mysql_fetch_assoc($this->result)){
            $this->arrayedResult[] = $data;
        }
        return $this->arrayedResult;
    }
    // 'Arrays' múltiplos resultados com uma key
    public function arrayResultsWithKey($key='id'){
        if(isset($this->arrayedResult)){
            unset($this->arrayedResult);
        }
        $this->arrayedResult = array();
        while($row = mysql_fetch_assoc($this->result)){
            foreach($row as $theKey => $theValue){
                $this->arrayedResult[$row[$key]][$theKey] = $theValue;
            }
        }
        return $this->arrayedResult;
    }
    // Returns último ID Insert
    public function lastInsertID(){
        return mysql_insert_id($this->databaseLink);
    }
    // Return número de rows
    public function countRows($from, $where=''){
        $result = $this->select($from, $where, '', '', false, 'AND','count(*)');
        return $result["count(*)"];
    }
    // Fecha das conecções
    public function closeConnection(){
        if($this->databaseLink){
			// Commit before closing just in case :)
			$this->commit();
            mysql_close($this->databaseLink);
        }
    }
}
