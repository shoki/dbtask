<?php

/* global config */
class Config {
    /* db backups in dbbackup format */
    public $backup_basedir = "/backup/db";
    public $debug = true;
    public $backup_suffix = '.gz';

	/* backup file types that get imported, in right order */
	public $backup_types_order = array ( 'structure', 'view', 'federated', 'data', 'trigger' );
}

?>
