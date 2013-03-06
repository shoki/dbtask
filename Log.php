<?php

class Log {
	private $logs  = array();

	public function __construct(Config $conf) {
		$this->conf = $conf;
		define_syslog_variables();
		openlog("DBTask", LOG_PID, LOG_DAEMON);
	}

	public function write($msg)
	{
		if (is_array($msg)) {
			foreach ($msg as $line) {
				$this->_log($line);
			}
		} else
			$this->_log($msg);
	}

	public function debug($msg) {
		syslog(LOG_DEBUG, $msg);
		if ($this->conf->debug)
			echo("DEBUG: ".$msg."\n");
	}

	private function _log($msg) {
		syslog(LOG_INFO, rtrim($msg));
		$out = sprintf("%s [%d] %s\n", date('Y-m-d H:i:s'), posix_getpid(), $msg);
		$this->logs[] = $out;
		echo $out;
	}

	public function get()  {
		foreach ($this->logs as $entry) {
			$text .= $entry;
		}
		return $text;
	}
}

?>
