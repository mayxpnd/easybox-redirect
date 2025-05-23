<?php
// Database configuration
// This file contains the database connection settings.

// Define database connection constants
define('DB_SERVER', 'localhost'); // Database server (e.g., localhost)
define('DB_USERNAME', 'root');    // Database username
define('DB_PASSWORD', '');        // Database password
define('DB_NAME', 'warranty_system'); // Database name

// The script including this file will be responsible for establishing the connection.
// Example:
// $link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// if($link === false){
//     die("ERROR: Could not connect. " . mysqli_connect_error());
// }
?>
