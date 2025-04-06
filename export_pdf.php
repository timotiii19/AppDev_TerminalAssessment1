<?php

require __DIR__ . '/vendor/autoload.php'; // Composer autoloader
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Handle Filters ---
$whereClauses = ["expenses.user_id = ?"];
$params = [$user_id];

if (!empty($_REQUEST['day_filter'])) {
    $whereClauses[] = "DATE(expenses.purchase_date) = ?";
    $params[] = $_REQUEST['day_filter'];
}
if (!empty($_REQUEST['week_filter'])) {
    $week = $_REQUEST['week_filter'];
    $year = date('Y');
    $week_number = (int)substr($week, -2);
    $startDate = new DateTime();
    $startDate->setISODate($year, $week_number);
    $endDate = clone $startDate;
    $endDate->modify('+6 days');
    $whereClauses[] = "DATE(expenses.purchase_date) BETWEEN ? AND ?";
    $params[] = $startDate->format('Y-m-d');
    $params[] = $endDate->format('Y-m-d');
}
if (!empty($_REQUEST['month_filter'])) {
    $whereClauses[] = "MONTH(expenses.purchase_date) = ?";
    $params[] = $_REQUEST['month_filter'];
}
if (!empty($_REQUEST['year_filter'])) {
    $whereClauses[] = "YEAR(expenses.purchase_date) = ?";
    $params[] = $_REQUEST['year_filter'];
}
if (!empty($_REQUEST['sort_category'])) {
    $whereClauses[] = "expenses.category_id = ?";
    $params[] = $_REQUEST['sort_category'];
}

// --- Fetch Expenses ---
$sql = "SELECT expenses.*, categories.name AS category_name 
        FROM expenses 
        JOIN categories ON expenses.category_id = categories.id 
        WHERE " . implode(" AND ", $whereClauses) . " 
        ORDER BY expenses.purchase_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Init PDF ---
$pdf = new \TCPDF();
$pdf->SetCreator('Expense Tracker');
$pdf->SetAuthor('YourApp');
$pdf->SetTitle('Expense Report');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// --- Add Logo ---
$logoPath = 'images/logo.png'; // make sure this exists
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 30);
}
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Ln(15);
$pdf->Cell(0, 10, 'Expenses Report', 0, 1, 'C');
$pdf->Ln(5);

// --- Optional Chart Image ---
if (!empty($_POST['chart_image'])) {
        $chartData = explode(',', $_POST['chart_image'])[1];
        $chartData = base64_decode($chartData);

        $tmpFile = tempnam(sys_get_temp_dir(), 'chart') . '.jpg';
        file_put_contents($tmpFile, $chartData);

        if (file_exists($tmpFile)) {
            $pdf->Image($tmpFile, 15, $pdf->GetY(), 180, 90, 'JPG'); // ✅ Use 'JPG' here
            $pdf->Ln(100);
            unlink($tmpFile);
        } else {
        $pdf->Cell(0, 10, 'Failed to load chart image.', 0, 1);
    }
}



// --- Build Table ---
$pdf->SetFont('helvetica', '', 11);
$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th><b>Category</b></th>
            <th><b>Description</b></th>
            <th><b>Total Amount</b></th>
            <th><b>Quantity</b></th>
            <th><b>Amount per Piece</b></th>
            <th><b>Payment Method</b></th>
            <th><b>Date</b></th>
        </tr>
    </thead>
    <tbody>';

$totalAmount = 0;
foreach ($expenses as $row) {
    $totalAmount += $row['total_amount'];
    $html .= '<tr>
        <td>' . htmlspecialchars($row['category_name']) . '</td>
        <td>' . htmlspecialchars($row['description']) . '</td>
        <td>$' . number_format($row['total_amount'], 2) . '</td>
        <td>' . htmlspecialchars($row['quantity']) . '</td>
        <td>$' . number_format($row['amount_per_piece'], 2) . '</td>
        <td>' . htmlspecialchars($row['payment_method']) . '</td>
        <td>' . htmlspecialchars($row['purchase_date']) . '</td>
    </tr>';
}

$html .= '<tr style="background-color:#f9f9f9;">
    <td colspan="2"><b>Total</b></td>
    <td><b>$' . number_format($totalAmount, 2) . '</b></td>
    <td colspan="4"></td>
</tr>';

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');

// --- Output PDF ---
$pdf->Output('Expenses_Report.pdf', 'D');
exit();
