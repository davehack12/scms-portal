<?php

$host = "sql305.infinityfree.com";
$username = "if0_41960465";
$password = "AWcdJ2dufQsI9";
$dbname = "if0_41960465_SCM";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>
