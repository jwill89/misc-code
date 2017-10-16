<?php

/* 
 * Database Connection
 * Created By: James Will
 * Created Date: 2016-12-07
 * Last Updated: 2016-12-19
 */

class DB {

    // Access Through Instance
    private static $instance = NULL;

    // Prevent Use of new DB()
    private function __construct() {}
    private function __clone() {}

    public static function getInstance() {
        if(!isset(self::$instance)) {
            $host = "host";
            $database = "dbname";
            $username = "username";
            $password = "password";
            $charset = "utf8";
            $pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            $pdo_options[PDO::ATTR_EMULATE_PREPARES] = false;

            self::$instance = new PDO("mysql:host=$host;dbname=$database;charset=$charset", $username, $password, $pdo_options);
        }
        return self::$instance;
    }

}
