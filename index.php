<?php
require_once 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$bill_stmt = $conn->prepare("
    SELECT b.*, t.rate, t.tariff_type 
    FROM bills b
    JOIN tariffs t ON b.tariff_id = t.tariff_id
    WHERE b.user_id = ? AND b.status = 'pending'
    ORDER BY b.bill_period_end DESC
    LIMIT 1
");
$bill_stmt->bind_param("i", $user_id);
$bill_stmt->execute();
$current_bill = $bill_stmt->get_result()->fetch_assoc();
$history_stmt = $conn->prepare("
    SELECT b.*, t.rate, t.tariff_type 
    FROM bills b
    JOIN tariffs t ON b.tariff_id = t.tariff_id
    WHERE b.user_id = ?
    ORDER BY b.bill_period_end DESC
");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$payment_history = $history_stmt->get_result();
$consumption_stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(b.bill_period_end, '%b') AS month,
        b.units_consumed
    FROM bills b
    WHERE b.user_id = ?
    ORDER BY b.bill_period_end
    LIMIT 6
");
$consumption_stmt->bind_param("i", $user_id);
$consumption_stmt->execute();
$consumption_data = $consumption_stmt->get_result();
$chart_labels = [];
$chart_values = [];
while ($row = $consumption_data->fetch_assoc()) {
    $chart_labels[] = $row['month'];
    $chart_values[] = $row['units_consumed'];
}
$notifications_stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result();
$rewards_stmt = $conn->prepare("
    SELECT 
        SUM(points_earned)  AS current_points
    FROM reward_points
    WHERE user_id = ?
");
$rewards_stmt->bind_param("i", $user_id);
$rewards_stmt->execute();
$rewards = $rewards_stmt->get_result()->fetch_assoc();
$reward_points = $rewards['current_points'] ?? 0;
$unread_stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result()->fetch_assoc();
$unread_notifications = $unread_result['unread_count'] ?? 0; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EBMS - Electricity Bill Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #98a1d8;
            --secondary: #e7e8ef;
            --accent: #e4e6ef;
            --light: #f5f5f5;
            --dark: #212121;
            --success: #66bb6a;
            --warning: #ffa726;
            --danger: #ef5350;
            --grey: #000000;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fafafa;
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            flex: 1;
        }
        
        header {
            background-color: rgba(245, 245, 245, 0.156);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            width:50px;
            margin-right: 10px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark);
        }
        
        .welcome-message {
            text-align: center;
            flex-grow: 1;
        }
        
        .welcome-message h2 {
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .header-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .icon-badge {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .icon-badge:hover {
            transform: translateY(-3px);
        }
        
        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .main-content {
             padding-bottom: 20px;
            
        }
        
        .current-bill-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            text-align: center;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeIn 0.5s ease-out;
        }
        
        .current-bill-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .bill-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark);
            margin: 1rem 0;
            animation: pulse 2s infinite;
        }
        .icon-badge i.fa-bell {
    color: black;
}
        .bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .bill-detail-item {
            background-color: var(--light);
            padding: 0.8rem;
            border-radius: 8px;
        }
        
        .bill-detail-item span {
            display: block;
            font-size: 0.9rem;
            color: var(--grey);
        }
        
        .bill-detail-item strong {
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        .pay-btn {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .pay-btn:hover {
            background-color: #57a358;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(102, 187, 106, 0.3);
        }
        
        .consumption-analysis {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            animation: fadeIn 0.7s ease-out;
        }
        
        .consumption-analysis h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-container {
            height: 250px;
            margin-top: 1.5rem;
            position: relative;
        }
        
        footer {
            background-color: rgba(61, 53, 53, 0.14);
            backdrop-filter: 2px;;
            padding: 1rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 100;
        }
        
        .footer-nav {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        
        .footer-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .footer-nav-item i {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .footer-nav-item span {
            font-size: 0.8rem;
        }
        
        .footer-nav-item.active, .footer-nav-item:hover {
            color: var(--primary);
        }
        
        .notifications-page {
            display: none;
            padding: 2rem 0;
        }
        
        .notification-item {
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        .notification-icon {
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .notification-content h4 {
            margin-bottom: 5px;
            color: black;
        }
        
        .notification-content p {
            color: black;
            font-size: 0.9rem;
        }
        
        .notification-time {
            margin-left: auto;
            color: var(--grey);
            font-size: 0.8rem;
        }
        
        .tips-page {
            display: none;
            padding: 2rem 0;
        }
        
        .tips-category {
            margin-bottom: 2rem;
        }
        
        .tips-category h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--light);
        }
        
        .tip-item {
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .tip-icon {
            color: var(--success);
            font-size: 1.2rem;
            margin-top: 3px;
        }
        
        .tip-content h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .tip-content p {
            color: var(--grey);
            font-size: 0.9rem;
        }
        
        .history-page {
            display: none;
            padding: 2rem 0;
        }
        .abc{
            color:white;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            
        }
        
        .history-table th, .history-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--light);
        }
        
        .history-table th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
        }
        
        .history-table tr:last-child td {
            border-bottom: none;
        }
        
        .history-table tr:hover {
            background-color: rgba(92, 107, 192, 0.05);
        }
        
        .paid-status {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .paid {
            background-color: rgba(102, 187, 106, 0.2);
            color: var(--success);
        }
        
        .pending {
            background-color: rgba(255, 167, 38, 0.2);
            color: var(--warning);
        }
        
        .page-active {
            display: block !important;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .welcome-message h2 {
                font-size: 1rem;
            }
            
            .bill-details {
                grid-template-columns: 1fr;
            }
            
            .history-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="logo.png" alt="EBMS Logo">
                    <span class="logo-text">EBMS</span>
                </div>
                
                <div class="welcome-message">
                    <h2>Welcome, <span id="display-username"><?= htmlspecialchars($user['full_name']) ?></span></h2>
                </div>
                
                <div class="header-icons">
                    <div class="icon-badge" >
                    <a href="#notifications-page" class="footer-nav-item icon-badge" onclick="showPage('notifications')">
                
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
    <span class="badge-count"><?= htmlspecialchars($unread_notifications) ?></span></a>
<?php endif; ?>
                    </div>
                    
                    <div class="icon-badge" onclick="showRewardPoints()">
                        <i class="fas fa-coins"></i>
                        <?php if ($reward_points > 0): ?>
                            <span class="badge-count"><?= $reward_points ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="icon-badge" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="main-content page-active" id="main-page">
            <?php if ($current_bill): ?>
            <div class="current-bill-card">
                <h3>Current Bill</h3>
                <div class="bill-amount">₹<?= number_format($current_bill['amount'], 2) ?></div>
                
                <div class="bill-details">
                    <div class="bill-detail-item">
                        <span>Due Date</span>
                        <strong><?= date('M d, Y', strtotime($current_bill['due_date'])) ?></strong>
                    </div>
                    <div class="bill-detail-item">
                        <span>Units Consumed</span>
                        <strong><?= $current_bill['units_consumed'] ?> kWh</strong>
                    </div>
                    <div class="bill-detail-item">
                        <span>Tariff Rate</span>
                        <strong>₹<?= number_format($current_bill['rate'], 2) ?>/kWh</strong>
                    </div>
                    <div class="bill-detail-item">
                        <span>Billing Period</span>
                        <strong><?= date('M d', strtotime($current_bill['bill_period_start'])) ?> - <?= date('M d, Y', strtotime($current_bill['bill_period_end'])) ?></strong>
                    </div>
                </div>
                
                <button class="pay-btn" onclick="payBill(<?= $current_bill['bill_id'] ?>)">
                    <i class="fas fa-rupee-sign"></i> Pay Now
                </button>
            </div>
            <?php else: ?>
            <div class="current-bill-card">
                <h3>No Pending Bills</h3>
                <p>You don't have any pending bills at this time.</p>
            </div>
            <?php endif; ?>
            
            <div class="consumption-analysis">
                <h3><i class="fas fa-chart-line"></i> Consumption Analysis</h3>
                <?php if ($current_bill): ?>
                    <p>Your electricity usage this month: <strong><?= $current_bill['units_consumed'] ?> kWh</strong></p>
                <?php else: ?>
                    <p>No recent consumption data available</p>
                <?php endif; ?>
                <div class="chart-container">
                    <canvas id="consumptionChart"></canvas>
                </div>
            </div>
        </section>
        
        <section class="notifications-page" id="notifications-page">
            <h2 style="margin-bottom: 1.5rem; color: var(--primary);">Notifications</h2>
            
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                <div class="notification-item">
                    <div class="notification-icon">
                        <i class="fas fa-<?= 
                            $notification['notification_type'] === 'payment' ? 'rupee-sign' : 
                            ($notification['notification_type'] === 'reward' ? 'coins' : 'bolt') 
                        ?>"></i>
                    </div>
                    <div class="notification-content">
                        <h4><?= htmlspecialchars($notification['title']) ?></h4>
                        <p><?= htmlspecialchars($notification['message']) ?></p>
                    </div>
                    <div class="notification-time">
                        <?= date('M j, g:i a', strtotime($notification['created_at'])) ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="abc">No notifications found.</p>
            <?php endif; ?>
        </section>

        <section class="tips-page" id="tips-page">
            <h2 style="margin-bottom: 1.5rem; color: var(--primary);">Energy Saving Tips</h2>
            
            <div class="tips-category">
                <h3><i class="fas fa-snowflake"></i> Cooling Tips</h3>
                
                <div class="tip-item">
                    <div class="tip-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Set AC temperature to 24°C</h4>
                        <p>Each degree lower increases energy consumption by 3-5%</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Use ceiling fans with AC</h4>
                        <p>Allows you to raise the thermostat setting by 4°C with no reduction in comfort</p>
                    </div>
                </div>
            </div>
            
            <div class="tips-category">
                <h3><i class="fas fa-lightbulb"></i> Lighting Tips</h3>
                
                <div class="tip-item">
                    <div class="tip-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Switch to LED bulbs</h4>
                        <p>LEDs use 75% less energy and last 25 times longer than incandescent lighting</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="tip-content">
                        <h4>Use natural light during day</h4>
                        <p>Open curtains and blinds to reduce need for artificial lighting</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="history-page" id="history-page">
            <h2 style="margin-bottom: 1.5rem; color: var(--primary);">Payment History</h2>
            
            <?php if ($payment_history->num_rows > 0): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Bill Period</th>
                        <th>Amount</th>
                        <th>Transaction ID</th>
                        <th>Date Paid</th>
                        <th>Status</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
    <?php while ($bill = $payment_history->fetch_assoc()): ?>
    <tr>
        <td><?= date('M d', strtotime($bill['bill_period_start'])) ?> - <?= date('M d, Y', strtotime($bill['bill_period_end'])) ?></td>
        <td>₹<?= number_format($bill['amount'], 2) ?></td>
        <td><?= $bill['transaction_id'] ?: 'N/A' ?></td>
        <td><?= $bill['paid_date'] ? date('M d, Y', strtotime($bill['paid_date'])) : 'Not paid' ?></td>
        <td>
            <span class="paid-status <?= $bill['status'] ?>">
                <?= ucfirst($bill['status']) ?>
            </span>
        </td>
        <td>
            <?php if ($bill['status'] === 'paid'): ?>
                <a href="generate_invoice.php?bill_id=<?= $bill['bill_id'] ?>" class="download-btn">
                    <i class="fas fa-download"></i> Download
                </a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
            </table>
            <?php else: ?>
                <p>No payment history found.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-nav">
                <a href="#main-page" class="footer-nav-item active" onclick="showPage('main')">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#tips-page" class="footer-nav-item"onclick="showPage('tips')" >
                    <i class="fas fa-lightbulb"></i>
                    <span>Tips</span>
                </a>
                <a href="#history-page" class="footer-nav-item" onclick="showPage('history')">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
            </div>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('consumptionChart').getContext('2d');
            const consumptionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Units Consumed (kWh)',
                        data: <?= json_encode($chart_values) ?>,
                        backgroundColor: '#98a1d8',
                        borderColor: '#7d8ac9',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'kWh'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
            showPage('main');
        });
        function showPage(page) {
          
            document.querySelectorAll('.main-content > section').forEach(section => {
                section.classList.remove('page-active');
            });
            document.getElementById(page + '-page').classList.add('page-active');
            document.querySelectorAll('.footer-nav-item').forEach(item => {
                item.classList.remove('active');
            });
            const navItems = document.querySelectorAll('.footer-nav-item');
            navItems.forEach(item => {
                if (item.getAttribute('onclick').includes(page)) {
                    item.classList.add('active');
                }
            });
        }
        function showRewardPoints() {
            alert('You have <?= $reward_points ?> reward points!\n\nEarn more points by paying bills at stimulated time');
        }
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'login.php';
            }
        }

        function payBill(billId) {
    fetch('process_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'bill_id=' + billId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const downloadConfirmed = confirm(
                'Payment successful! Transaction ID: ' + data.transaction_id + 
                '\n\nDo you want to download the invoice?'
            );
            
            if (downloadConfirmed) {
                window.open('generate_invoice.php?bill_id=' + data.bill_id, '_blank');
            }
            location.reload();
        } else {
            alert('Payment failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Payment processing error');
    });
}
    </script>
</body>
</html>