<?php
    session_start();

    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $employee_id = $db->quote($_POST["employee_id"]);
    $full_name = $db->quote($_POST["full_name"]);
    $position = $db->quote($_POST["position"]);
    $department = $db->quote($_POST["department"]);
    $gross_salary = $db->quote($_POST["gross_salary"]);
    $deductions = $db->quote($_POST["deductions"]);
    $status = $db->quote($_POST["status"]);
    $hire_date = $db->quote($_POST["hire_date"]);


    // echo "Amount: $amount, Category: $category";
    $add_employee_sql = $db->exec("INSERT INTO employees(employee_id, full_name, position, department, gross_salary, deductions, status, hire_date) VALUES($employee_id, $full_name, $position, $department, $gross_salary, $deductions, $status, $hire_date)");


    header("Location: payroll.php");    
?>