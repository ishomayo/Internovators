<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $amount = $_POST["amount"];
    $description = $db->quote($_POST["description"]);

    if($_POST["newCategory"]) {
        $category = strtolower($db->quote($_POST["newCategory"]));
    } else {
        if($_POST["newCategory"] != "Select Category"){
            $category = strtolower($db->quote($_POST["category"]));
        }
    }


    // echo "Amount: $amount, Category: $category";
    $add_expense_sql = $db->exec("INSERT INTO expenses(amount, description, category) VALUES($amount, $description, $category)");


    header("Location: expenses.php");    
?>