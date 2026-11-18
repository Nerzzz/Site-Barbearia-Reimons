<?php
    $servername = "sql200.byethost7.com";
    $dbusername = "b7_43446412";
    $dbpssw = "Ep_27108080";
    $dbname = "b7_43446412_barbearia_reimons";

    $conn = new mysqli($servername, $dbusername, $dbpssw, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
?>