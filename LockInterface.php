<?php

interface LockInterface {
	public function lock();
	public function unlock($force = false);
	public function isLocked();
}

?>
