<?php
    $servername = "sql200.byethost7.com";
    $username = "b7_43446412";
    $password = "Ep_27108080";
    $dbname = "b7_43446412_barbearia_reimons";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

	$conn->set_charset('utf8mb4');
?>