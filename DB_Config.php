<?php

class DB_Config {
    public $host;
    public $user;
    public $pass;
    public $db  ;

	public function __construct ($host = false, $user = false, $pass = false, $db = false) {
		if ($host) $this->host = $host;
		if ($user) $this->user = $user;
		if ($pass) $this->pass = $pass;
		if ($db)   $this->db   = $db;
	}

}

?>
