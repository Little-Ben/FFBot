<?php
/*
    This file is part of FFBot

    Copyright (C) 2016-2017 Benjamin Schmitt

    FFBot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FFBot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FFBot. If not, see <http://www.gnu.org/licenses/>.
*/

class MySqlInterface {
    private $database;
    private $user;
    private $pwd;
    private $host;
    private $port;
    private $link;
    private $affectedRows;
    private $version;
    private $mysql;
    private $verbose;

    public function __construct($db_database, $db_user, $db_pwd, $db_host = 'localhost', $db_port = 3306) {

        $this->database = $db_database;
        $this->user = $db_user;
        $this->pwd = $db_pwd;
        $this->host = $db_host;
        $this->port = $db_port;
        $this->affectedRows = -1;
        $this->verbose=false;

        $this->mysql = new mysqli($db_host.":".$db_port, $db_user, $db_pwd, $db_database);
        if ($this->mysql->connect_errno) {
            echo "[DB] Failed to connect to MySQL: (" . $this->mysql->connect_errno . ") " . $this->mysql->connect_error;
            echo "<br>[DB] user: $db_user / db: $db_database / host: $db_host / port: $db_port";
        }
        $this->mysql->set_charset('utf8');
        $this->mysql->autocommit(FALSE);
        if ($this->verbose) echo "[DB] " . $this->mysql->host_info . "\n";

        if ($result = $this->mysql->query("SELECT @@autocommit")) {
            $row = $result->fetch_row();
            if ($this->verbose) echo "[DB] Autocommit is " .  $row[0];
            $result->free();
        }

    }

    public function executeStatement($sql) {
        $this->mysql->query($sql);
        $this->affectedRows = $this->mysql->affected_rows;
        if ($this->verbose) echo "[DB] " . $this->affectedRows . " rows affected\n";
        return $this->affectedRows;
    }

    public function getAffectedRows() {
        return $this->mysql->affected_rows;
        //return 0;
    }

    public function beginTrans() {
        //$this->mysql->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $this->mysql->begin_transaction();
        if ($this->verbose) echo "[DB] begin transaction\n";
    }

    public function commitTrans() {
        $this->mysql->commit();
        if ($this->verbose) echo "[DB] commit transaction\n";
    }

    public function rollbackTrans() {
        $this->mysql->rollback();
        if ($this->verbose) echo "[DB] rollback transaction\n";
    }

    public function setVerboseMode($useVerboseMode) {
        $this->verbose=$useVerboseMode;
    }

    public function queryResult($sql) {
        if ($result = $this->mysql->query($sql)) {
            $this->affectedRows = $result->num_rows;
            return $result;
        }
    }

    public function queryCell($sql) {
        if ($result = $this->mysql->query($sql)) {
            $this->affectedRows = $result->num_rows;

            $retVal="";
            while($row = $result->fetch_row()) {
                $retVal = $row[0]; //bei mehreren Zeilen, nur immer die letzte
            }
            $result->free();
            return $retVal;
        }
    }

    public function escape($string_to_escape) {
        return trim($this->mysql->real_escape_string($string_to_escape));
    }

    public function unescape($string_to_unescape) {
        return stripslashes($string_to_unescape);
    }

    public function error() {
        return $this->mysql->error();
    }
    public function __destruct() {
        //if ($this->verbose) echo $this->mysql->host_info . "\n";
        $this->mysql->close();
        $this->pwd = "";
        if ($this->verbose) echo "[DB] connection closed\n";
    }

} //Ende Klasse

?>
