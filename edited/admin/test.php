<?php
$servername = "localhost"; // Hostname
$username = "root"; // Default username for XAMPP
$password = ""; // Default password is usually empty for XAMPP
$dbname = "server"; // Replace with your existing database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully"; // Optional: To confirm connection
?>