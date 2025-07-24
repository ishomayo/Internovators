<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $budget = $_POST["budget"];

    $edit_budget = $db->exec("UPDATE monthly_budget SET budget = " . $budget);

    header("Location: expenses.php");

?>