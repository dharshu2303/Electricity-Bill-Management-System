<?php
require_once 'db_connect.php';
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
if (!isset($_GET['bill_id'])) {
    die('Invalid request');
}
$bill_id = $_GET['bill_id'];
$stmt = $conn->prepare("
    SELECT b.*, u.full_name, u.address, u.meter_number, t.tariff_type, t.rate 
    FROM bills b
    JOIN users u ON b.user_id = u.user_id
    JOIN tariffs t ON b.tariff_id = t.tariff_id
    WHERE b.bill_id = ?
");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    die('Bill not found');
}
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Invoice #'.$bill['bill_id'].'</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .details { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f5f5f5; text-align: left; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        .total { font-weight: bold; font-size: 1.1em; }
        .footer { margin-top: 30px; text-align: right; font-style: italic; color: #666; }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <h1>Electricity Bill Invoice</h1>
            <p>Invoice #'.$bill['bill_id'].'</p>
        </div>
        
        <div class="details">
            <div>
                <h3>Customer Details</h3>
                <p><strong>Name:</strong> '.htmlspecialchars($bill['full_name']).'</p>
                <p><strong>Address:</strong> '.htmlspecialchars($bill['address']).'</p>
                <p><strong>Meter No:</strong> '.htmlspecialchars($bill['meter_number']).'</p>
            </div>
            <div>
                <h3>Bill Details</h3>
                <p><strong>Billing Period:</strong> '.date('d M Y', strtotime($bill['bill_period_start'])).' to '.date('d M Y', strtotime($bill['bill_period_end'])).'</p>
                <p><strong>Due Date:</strong> '.date('d M Y', strtotime($bill['due_date'])).'</p>
                <p><strong>Paid On:</strong> '.date('d M Y', strtotime($bill['paid_date'])).'</p>
            </div>
        </div>
        
        <table>
            <tr>
                <th>Description</th>
                <th>Details</th>
            </tr>
            <tr>
                <td>Units Consumed</td>
                <td>'.$bill['units_consumed'].' kWh</td>
            </tr>
            <tr>
                <td>Tariff Rate</td>
                <td>Rs '.number_format($bill['rate'], 2).' per kWh</td>
            </tr>
            <tr>
                <td>Total Amount</td>
                <td>Rs '.number_format($bill['amount'], 2).'</td>
            </tr>
            <tr>
                <td>Transaction ID</td>
                <td>'.$bill['transaction_id'].'</td>
            </tr>
        </table>
        
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>Generated on '.date('d M Y H:i:s').'</p>
        </div>
    </div>
</body>
</html>';
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice_".$bill['bill_id'].".pdf", [
    "Attachment" => true 
]);
exit;
?>