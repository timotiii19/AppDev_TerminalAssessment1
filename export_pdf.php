<?php

require __DIR__ . '/vendor/autoload.php';
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
$logoPath = 'images/logo.png';
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
        $pdf->Image($tmpFile, 15, $pdf->GetY(), 180, 90, 'JPG');
        $pdf->Ln(100);
        unlink($tmpFile);
    } else {
        $pdf->Cell(0, 10, 'Failed to load chart image.', 0, 1);
    }
}

// --- Build Dynamic Table ---
$pdf->SetFont('helvetica', '', 10);
$pdf->Ln(5);

$cellWidths = [30, 40, 25, 15, 25, 30, 25]; // Adjust column widths
$cellAligns = ['C', 'C', 'C', 'C', 'C', 'C', 'C']; // you can tweak this per your needsS
$headers = ['Category', 'Description', 'Total Amount', 'Qty', 'Per Piece', 'Payment', 'Date'];

// --- Table Header ---
$pdf->SetFillColor(240, 240, 240); // Light gray background
$pdf->SetTextColor(0);
$pdf->SetFont('', 'B');

foreach ($headers as $i => $header) {
    $pdf->MultiCell($cellWidths[$i], 8, $header, 1, 'C', 1, 0, '', '', true); // Centered headers
}
$pdf->Ln();

// --- Table Rows ---
$pdf->SetFont('', '');
$pdf->SetFillColor(255, 255, 255);
$totalAmount = 0;

foreach ($expenses as $row) {
    $data = [
        $row['category_name'],
        $row['description'],
        '$' . number_format($row['total_amount'], 2),
        $row['quantity'],
        '$' . number_format($row['amount_per_piece'], 2),
        $row['payment_method'],
        $row['purchase_date'],
    ];

    // Calculate the max height required by any cell in this row
    $lineHeight = 6;
    $maxHeight = 0;
    foreach ($data as $i => $txt) {
        $nbLines = $pdf->getNumLines($txt, $cellWidths[$i]);
        $maxHeight = max($maxHeight, $nbLines * $lineHeight);
    }

    // Output the row with dynamic cell heights
    foreach ($data as $i => $txt) {
        $pdf->MultiCell($cellWidths[$i], $maxHeight, $txt, 1, $cellAligns[$i], 0, 0, '', '', true);
    }

    $pdf->Ln();
    $totalAmount += $row['total_amount'];
}

// --- Total Row ---
$pdf->SetFont('', 'B');
$pdf->SetFillColor(249, 249, 249);
$pdf->MultiCell($cellWidths[0] + $cellWidths[1], 8, 'Total', 1, 'R', 1, 0);
$pdf->MultiCell($cellWidths[2], 8, '$' . number_format($totalAmount, 2), 1, 'R', 1, 0);
for ($i = 3; $i < count($cellWidths); $i++) {
    $pdf->MultiCell($cellWidths[$i], 8, '', 1, '', 1, 0);
}
$pdf->Ln();

// --- Output PDF ---
$pdf->Output('Expenses_Report.pdf', 'D');
exit();
