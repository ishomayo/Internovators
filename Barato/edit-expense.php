<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id = $_POST["id"];
    $amount = $_POST["amount"];
    $description = $db->quote($_POST["description"]);
    $category = strtolower($db->quote($_POST["category"]));

    if($_POST["act"] == "Delete Expense"){
        echo "ADI AKO";
        $delete_expense = $db->exec("DELETE FROM expenses WHERE id = " . $id);
    }else{
        echo "EDIT AKO";
        $edit_expense = $db->exec("UPDATE expenses SET amount = $amount, description = $description, category = $category WHERE id = " . $id);
    }

?>