<?php
session_start();
require_once '../config/database.php';
require_once '../app/models/ReservationDAO.php';
require_once '../app/factories/DAOFactory.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Not authenticated');
}

$userId = $_SESSION['user_id'];
$db = Database::connect();
$reservationDAO = DAOFactory::reservationDAO($db);

// Get user reservations
$reservations = $reservationDAO->getUserReservations($userId);

// Calculate total
$total = 0;
foreach ($reservations as $r) {
    $total += floatval($r['price'] ?? 0);
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="parkalot_invoice.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Generate simple PDF content (we'll use a library-free approach)
// For a real PDF, you'd use TCPDF or FPDF library
// For now, we'll create an HTML that renders as PDF

// Instead, let's output HTML and let the browser handle PDF conversion
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ParkaLot Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #2c3e50; }
        .summary { background: #f8f9fa; padding: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .total { font-size: 24px; font-weight: bold; text-align: right; }
        @media print {
            body { margin: 0; }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
            setTimeout(function(){ window.close(); }, 100);
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>ðŸ…¿ï¸ ParkaLot Invoice</h1>
        <p>Date: <?php echo date('d/m/Y'); ?></p>
        <p>Customer ID: <?php echo htmlspecialchars($userId); ?></p>
    </div>
    
    <div class="summary">
        <p><strong>Total Reservations:</strong> <?php echo count($reservations); ?></p>
        <p><strong>Total Amount:</strong> Â£<?php echo number_format($total, 2); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Garage</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Price (£)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reservations)): ?>
                <tr><td colspan="5" style="text-align:center;">No reservations found</td></tr>
            <?php else: ?>
                <?php foreach ($reservations as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['reservation_id']); ?></td>
                    <td><?php echo htmlspecialchars($r['garage_name'] ?? 'N/A'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['start_time'])); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($r['end_time'])); ?></td>
                    <td>Â£<?php echo number_format(floatval($r['price'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="total">
        Total: Â£<?php echo number_format($total, 2); ?>
    </div>
    
    <p style="text-align:center; color:#999; margin-top:50px;">
        Thank you for using ParkaLot!
    </p>
</body>
</html>
