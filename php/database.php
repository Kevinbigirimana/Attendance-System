<?php
 // Import environment variables from connect.env 
$env = parse_ini_file(__DIR__ . '/../env/connect.env'); // path to your env file
// Use the values from the environment file to connect 
$conn = new mysqli( 
    $env['servername'], 
    $env['username'], 
    $env['password'], 
    $env['dbname'] 
); 
// Check connection
 if ($conn->connect_error) { 
die("Connection failed: " . $conn->connect_error); 
} 
?>