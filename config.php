<?php
    //This file is to setup a maser key for encryption and define the storage files.
    $env = parse_ini_file('.env');

    define("MASTER_KEY",$env["MASTER_KEY"]);
    define("UPLOAD_DIR","contents/");
    define("DB_DIR","DB/");

?>