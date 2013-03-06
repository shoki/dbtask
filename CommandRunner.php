<?php
class CommandRunner {
	protected $log = array();
	private $isRetry;

	public function getAndFlushLog() {
		$l = $this->log;
		$this->flushLog();
		return $l;
	}

	public function getLog() {
		return $this->log;
	}

	public function flushLog() {
		$this->log = array();
	}

	protected function logit($msg) {
		if (is_array($msg)) {
			foreach($msg as $entry) {
				if ($entry)
					$this->addLogEntry($entry);
			}
		} else
			$this->addLogEntry($msg);
	}

	protected function addLogEntry($msg) {
		$this->log[] = rtrim($msg);
	}

	protected function run($cmd) {
		$pipes;
		$ret = -1;

		$desc = array (
			0 => array ("pipe", "r"),
			1 => array ("pipe", "w"),
			2 => array ("pipe", "w")
			);

		$proc = proc_open($cmd, $desc, $pipes);
		if (is_resource($proc)) {
			if ($out = stream_get_contents($pipes[1]))
				$this->logit(explode("\n", $out));
			if ($out = stream_get_contents($pipes[2]))
				$this->logit(explode("\n", $out));

			/* close pipes before continuing */
			foreach ($pipes as $idx => $value) {
				fclose($pipes[$idx]);
			}

			$ret = proc_close($proc);
		}

		if ($ret == 0) {
			$this->isRetry = false;
			return true;
		} else {
			if($this->isRetry) {
				$this->logit("Command (".$cmd.") failed again.. [".$ret."] giving up!");
				// cleanup
				$this->isRetry = false;
				return false;
			}
			else {
				$this->logit("Command ($cmd) failed. exit-code: $ret retrying in 10 seconds!");
				$this->isRetry = true;
				sleep(10);
				return $this->run($cmd);
			}
		}
	}

}

?>
