<?php

class MySQLCommand extends CommandRunner {
	public function __construct($host, $auth, $log) {
		$this->logger = $log;
		$this->host = $host;
		$this->user = $auth['user'];
		$this->pass = $auth['pass'];
		$this->mysqlcmd = "mysql -h ${host} -u ${auth['user']} -p${auth['pass']}";
	}

	protected function addLogEntry($msg) {
		$this->logger->write('['.__CLASS__.'] '.$msg);
	}

	public function runScript($file, $db = "") {
		/* run script with force flag which skips over errors */
		return $this->run("cat ${file} | ".$this->mysqlcmd." -f ${db}");
	}

	/* returns an array with the output of the given command */
	public function runSQL($cmd, $db = "") {
		return $this->run("echo '".$cmd."' | ".$this->mysqlcmd." -f ${db}");
	}

	public function import($file, $db) {
		$filename = glob($file);
		if (!empty($filename))
			$ret = $this->run("zcat ${file} | ".$this->mysqlcmd." ${db}");
		else
			return false;
		return $ret;
	}
}

?>
