<?php

include "config.class.php";

class DB{
	private $is_connected;
	private $link;

	function __construct(){
		$this->is_connected = false;

		$this->link = mysql_connect(Config::Get('DB_HOST'),Config::Get('DB_USER'),Config::Get('DB_PASS'));
		if($this->link!==false) {
			$status = mysql_select_db(Config::Get('DB_NAME'),$this->link);
			if($status !== false){
				$this->is_connected = true;
			}
		}
	}

	function escape($param) {
		return mysql_real_escape_string($param,$this->link);
	}

	function getLastID() {
		return mysql_insert_id($this->link);
	}

	function runQuery($query) {
		$result = false;
		if($this->is_connected) {
			$result = mysql_query($query,$this->link);
		}
		return $result;
	}

	function getRow($query) {
		$row = array();
		if($this->is_connected) {
			$result = mysql_query($query,$this->link);
			if($result && mysql_num_rows($result) > 0)
				$row = mysql_fetch_assoc($result);
		}
		return $row;
	}

	function getRows($query) {
		$rows = array();
		if($this->is_connected) {
			$result = mysql_query($query,$this->link);
			if($result && mysql_num_rows($result) > 0) {
				while($row = mysql_fetch_assoc($result)) {
					$rows[] = $row;
				}
			}
		}
		return $rows;
	}

	public function buildINSERT($table,$vars) {
		if(empty($table) or empty($vars)) return false;

		$f_keys = array_keys($vars);
		if(!empty($f_keys)) {
			foreach($f_keys as $k => $v) {
				$f_keys[$k] = $this->escape($v);
			}
		}

		if(!empty($vars)) {
			foreach($vars as $k => $v) {
				$vars[$k] = "'".$this->escape($v)."'";
			}
		}

		$sql = sprintf("INSERT INTO %s (%s) VALUES (%s)",
				$this->escape($table),
				implode(',',$f_keys),
				implode(',',$vars)
			);

		return $sql;
	}

	public function buildUPDATE($table,$vars,$where = false) {
		$sql = false;
		if(empty($table) or empty($vars)) return false;

		$update_sql = "";
		$update = array();
		if(!empty($vars)) {
			foreach($vars as $k => $v) {
				$update[] = $k."='".$this->escape($v)."'";
			}
		}
		if(!empty($update)) {
			$update_sql = implode(',',$update);
		}

		$where_arr = array();
		$where_sql = "";
		if(!empty($where)) {
			foreach($where as $k => $v) {
				$where_arr[] = $k."='".$this->escape($v)."'";
			}
		}
		if(!empty($where_arr)) {
			$where_sql = " WHERE ".implode(' AND ',$where_arr);
		}

		if(!empty($update_sql)) {
			$sql = sprintf("UPDATE %s set %s %s",
					$this->escape($table),
					$update_sql,
					$where_sql
				);
		}

		return $sql;
	}
}

?>
