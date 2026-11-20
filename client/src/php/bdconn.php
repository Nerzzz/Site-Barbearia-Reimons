<?php
    $servername = "localhost";
    $dbusername = "barbearia";
    $dbpssw = "barbearia";
    $dbname = "barbearia_reimons";

    $conn = new mysqli($servername, $dbusername, $dbpssw, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>