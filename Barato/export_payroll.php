<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payroll_report.csv"');

try {
    $db = new PDO("mysql:dbname=barato_db;host=192.168.1.61", "internnovators", "Internnovator123!");
    
    // Get employee data
    $query = "SELECT 
        employee_id,
        full_name,
        position,
        department,
        gross_salary,
        deductions,
        (gross_salary - deductions) as net_salary
    FROM employees 
    WHERE status = 'active'";
    
    $result = $db->query($query);
    
    // Create CSV
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Employee ID', 'Name', 'Position', 'Department', 'Gross Salary', 'Deductions', 'Net Salary']);
    
    // Add data rows
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fclose($output);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>