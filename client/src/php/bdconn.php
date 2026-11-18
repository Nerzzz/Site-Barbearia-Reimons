<?php
    $servername = "localhost";
    $username = "barbearia";
    $password = "barbearia";
    $dbname = "barbearia_reimons";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>