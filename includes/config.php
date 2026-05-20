<?php
$host     = "localhost";
$username = "root";
$password = "";          // XAMPP default has NO password — leave blank
$dbname   = "scms_db";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>