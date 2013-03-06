<?php

class MyDB {
	private $db;
	const QUERY_DEBUG = false;

	public function __construct(DB_Config $cfg) {
		$this->dbcfg = $cfg;
	}

	public function connect() {
		$this->db = mysqli_init();
		$this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
		$this->db->real_connect($this->dbcfg->host, $this->dbcfg->user,
				$this->dbcfg->pass, $this->dbcfg->db);
		if (mysqli_connect_errno()) 
			throw new MyDBException("could not connect to database: ".mysqli_connect_error());
		$this->connected = true;
		return true;
	}

	public function query($qry) {
		if (self::QUERY_DEBUG) echo("QUERY=".$qry."\n");
		$this->connect();
		$ret = $this->db->query($qry);
		if ($this->db->error) trigger_error($this->db->error);
		else $this->affected_rows = $this->db->affected_rows;
		$this->close();
		return $ret;
	}

	public function close() {
		if ($this->connected) $this->db->close();
		$this->connected = false;
		unset($this->db);
	}

}

?>
