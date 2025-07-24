<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // $id = $_POST
    $amount = $_POST["amount"];
    $description = $db->quote($_POST["description"]);
    $category = strtolower($db->quote($_POST["category"]));

    $edit_expense = $db->exec("UPDATE expenses SET amount = $amount, description = $description, category = $category WHERE id = " . $_POST["expenseId"]);
?>