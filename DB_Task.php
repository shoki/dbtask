<?php

class DB_Task {
	private $starttime;
	private $errors = 0;

	public function __construct() {
		$this->db = new MyDB(new TaskDB_Config());
		$this->cfg = new Config();
		$this->log = new Log($this->cfg);

	}

	private function getTask($type, $name) {
		$sql = "SELECT * FROM tasks WHERE type='".$type."' ";
		$sql.= "AND name='".$name."'";
		$res = $this->db->query($sql);

		if ($res) {
			if ($row = $res->fetch_assoc()) {
				return $row;
			}
		}
		return false;
	}

	private function getAuth($id) {
		$sql = "SELECT user,pass FROM auth WHERE id=".$id;
		$res = $this->db->query($sql);
		if ($res) {
			/* just one row maximum */
			$ret = $res->fetch_assoc();
			if ($ret)
				return $ret;
			else
				return false;
		}
		return false;
	}

	private function getFiles($id) {
		$sql = "SELECT name FROM tables WHERE taskid=$id";
		$res = $this->db->query($sql);
		if ($res) {
			while($row = $res->fetch_assoc()) {
				$ret[] = explode(".", $row['name']);	/* format: dbname.tablename */
			}
			return $ret;
		}
		return false;
	}

	private function getBackupDir($days) {
		$yesterday = date('Ymd', $this->starttime - (24*60*60 * $days));
		/* assume we need the last backup, mabye use Session API from
		 * DBBACKUP? */
		$dir = $this->cfg->backup_basedir.'/'.$yesterday."_00/";
		if (!file_exists($dir)) throw new Exception($dir." not found");
		return $dir;
	}

	private function runScripts($id, $type) {
		$sql = "SELECT * FROM scripts WHERE taskid=".$id." AND type='".$type."' AND enabled=1";
		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $res->fetch_assoc()) {
				$this->log->write("Run ".$type." script id: ".$id);
				/* save the script to a tempfile to avoid shell escaping
				 * problems */
			    /* XXX: what about file security here ??? */
				$file = tempnam("/tmp", "dbtask_script_".$type."_taskid_".$id);
				file_put_contents($file, $row['script']);
				$this->mysql->runScript($file);
				unlink($file);
			}
		}
	}

	private function wildcardImport($myconn, $task, $db, $path, $wildcard) {
		/* get ignores */
		$ignore = array();
		$ret = $this->db->query("SELECT type, table_regexp FROM tables_ignore WHERE taskid=".$task['id']);
		if ($ret)
			while ($row = $ret->fetch_assoc()) $ignore[$row['type']][] = $row['table_regexp'];

		// $oldtables = array();

		if ($wildcard != '*') {
			/* single table import */
			$wildcard .= '*';
		} else {
			/* first get a list of current database tables */
			/*
			$ret = $myconn->query("SHOW TABLE STATUS FROM {$db}");
			if ($ret)
				while ($row = $ret->fetch_assoc()) $oldtables[$row['Name']] = $row;
			*/
		}

		/* glob files to import and remember tables that should be
		 * dropped */
		$files = glob($path.'/'.$wildcard);
		if (empty($files))
			throw new Exception("empty backup detected");

		foreach ($files as $filename) {
			/* filename format:
			 * 	tablename.dumptype.suffix
			 */
			$entry = explode('.', basename($filename));
			$newtables[$entry[1]][] = $entry[0];
		}

		foreach ($this->cfg->backup_types_order as $type) {
			if (!isset($newtables[$type])) continue;
			foreach ($newtables[$type] as $name) {
				$skip = false;
				if (isset($ignore[$type])) {
					foreach ($ignore[$type] as $entry) {
						if (preg_match($entry, $name)) {
							$this->log->write("Skip ".$type." of ".$name);
							$skip = true;
							break;
						}
					}
				}
				if (!$skip)
					$this->importTable($path, $name, $type, $db);

				//if (isset($oldtables[$name]))
				//	unset($oldtables[$name]);
			}
		}


		/* drop leftover */
		/* DEPRECATED DUE TO DROP ALL
		if ($wildcard == '*') {
			foreach ($oldtables as $name => $spec) {
				/* catch VIEWs and drop them */
				/*
				if ($spec['Engine'] == '' && $spec['Comment'] == 'VIEW') {
					$myconn->query("DROP VIEW {$db}.{$name}");
					$this->log->write("Droped view $name");
				} else {
					$myconn->query("DROP TABLE {$db}.{$name}");
					$this->log->write("Droped table $name");
				}
			}
		}
		*/
	}

	private function importTable($path, $table, $type, $db) {
		$start = time();
		$ret = $this->mysql->import($path.'/'.$table.'.'.$type.$this->cfg->backup_suffix, $db);
		$end = time();
		$duration = $end - $start;
		if (! $ret ) {
			$this->errors++;
			$this->log->write("Failed to import ".$type." of ".$table." into ".$db);
			return false;
		} else {
			$this->log->write("Imported ".$type." of ".$table." into ".$db." in ".$duration."s");
			return true;
		}
	}

	private function importFiles($backupdir, $files, $task, $auth) {
		foreach($files as $file) {
			$db = $file[0];
			$filename = $file[1];
			$path = $backupdir.$task['source_host'].".".$db;

			try {
				$dbauth = new DB_Config($task['destination_host'], $auth['user'], $auth['pass']);
				$myconn = new MyDB($dbauth);

				/* if its a wildcard import, drop old database to get rid of
				 * tables that don't exist in the backup anymore */
				if($filename == "*") {
					$this->log->write("Dropping database ".$db);
					$myconn->query("SET FOREIGN_KEY_CHECKS=0");
					$myconn->query("DROP DATABASE ".$db);
				}

				/* Create Database if dropped or not existant */
				$myconn->query("CREATE DATABASE IF NOT EXISTS ".$db);

				$this->wildcardImport($myconn, $task, $db, $path, $filename);
			} catch (Exception $e) {
				$this->log->write("Could not run import for $db: ".$e->getMessage());
			}
		}
	}


	public function runTask($task) {
		try {
			/* avoid running task parallely */
			$lock = new Lock(basename(__FILE__).'_'.$task['name']);
			$lock->lock();

			$this->log->write("Run task ".$task['name']);

			/* get authentication for destination host */
			$auth = $this->getAuth($task['destination_authid']);
			if (!$auth) throw new Exception("No authentication found for this host!");

			$files = $this->getFiles($task['id']);

			$this->mysql = new MySQLCommand($task['destination_host'], $auth, $this->log);

			/* get backup directory depending on the age of the requested backup */
			$backupdir = $this->getBackupDir($task['backup_age']);

			/* run pre script */
			$this->runScripts($task['id'], 'pre');

			$this->importFiles($backupdir, $files, $task, $auth);

			/* run post script */
			$this->runScripts($task['id'], 'post');

			unset($this->mysql);

			$lock->unlock();
		} catch (LockException $e) {
			$this->log->write("Error running task: already running");
			return false;
		} catch (Exception $e) {
			$this->log->write("Error running task: ".$e->getMessage());
			$lock->unlock();
			return false;
		}
	}

	private function logSuccess() {
		$db = new MyDB(new LogDB_Config());
		$db->query("REPLACE INTO log_dbtask SET `date` = NOW(), status = ".($this->errors > 0 ? 0 : 1));
	}

	public function run($argv) {
		if (!$this->db) {
			$this->errors++;
			$this->log->write("Could not connect to management database!");
			return false;
		}
		if (!is_array($argv) || empty($argv)) {
			$this->errors++;
			$this->log->write("No arguments given!");
			return false;
		}
		/* assemble task list */
		foreach ($argv as $arg) {
			$task = $this->getTask('import', $arg);
			if ($task) $tasks[] = $task;
			else {
				$this->errors++;
				$this->log->write("No task named '$arg' found.");
			}
		}
		if (!$tasks) {
			$this->errors++;
			$this->log->write("Nothing to do.");
			return false;
		}

		/* remember start time for later calculations (backup age) */
		$this->starttime = time();
		/* execute task list */
		foreach ($tasks as $task) {
			$this->runTask($task);
		}

		$this->logSuccess();
	}
}

?>
