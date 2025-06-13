<?php
require_once 'db_connect.php';
session_start();
if ($_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $meter_number = $_POST['meter_number'];
    $account_number = $_POST['account_number'];
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, address, meter_number, account_number, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')");
    $stmt->bind_param("sssssss", $username, $password, $full_name, $email, $address, $meter_number, $account_number);
    $stmt->execute();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tariff'])) {
    $tariff_type = $_POST['tariff_type'];
    $rate = $_POST['rate'];
    $effective_date = $_POST['effective_date'];
    
    $stmt = $conn->prepare("UPDATE tariffs SET rate = ?, effective_date = ? WHERE tariff_type = ?");
    $stmt->bind_param("dss", $rate, $effective_date, $tariff_type);
    $stmt->execute();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reading'])) {
    $user_id = $_POST['user_id'];
    $reading_value = $_POST['reading_value'];
    $reading_date = $_POST['reading_date'];
    
    $stmt = $conn->prepare("INSERT INTO meter_readings (user_id, reading_date, reading_value) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $user_id, $reading_date, $reading_value);
    $stmt->execute();
    $tariff = $conn->query("SELECT rate FROM tariffs WHERE tariff_type = 'residential'")->fetch_assoc();
    $amount = $reading_value * $tariff['rate'];
    
    $conn->query("INSERT INTO bills (user_id, bill_period_start, bill_period_end, due_date, units_consumed, tariff_id, amount) 
                 VALUES ($user_id, DATE_SUB('$reading_date', INTERVAL 1 MONTH), '$reading_date', DATE_ADD('$reading_date', INTERVAL 15 DAY), $reading_value, 1, $amount)");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $bill_id = $_POST['bill_id'];
    $status = $_POST['status'];
    
    if ($status === 'paid') {
        $stmt = $conn->prepare("UPDATE bills SET status = 'paid', paid_date = CURDATE() WHERE bill_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE bills SET status = 'pending', paid_date = NULL WHERE bill_id = ?");
    }
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    $user_id = $_POST['user_id'];
    $bill_id = $_POST['bill_id'];
    $bill = $conn->query("SELECT * FROM bills WHERE bill_id = $bill_id")->fetch_assoc();
    $user = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();
    $title = "Payment Reminder";
    $message = "Dear " . $user['full_name'] . ", your bill of ₹" . number_format($bill['amount'], 2) . " is pending. Please pay before " . date('d M Y', strtotime($bill['due_date'])) . " to avoid late fees.";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, 'payment')");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
}
$customers = $conn->query("SELECT * FROM users WHERE user_type = 'customer'");
$tariffs = $conn->query("SELECT * FROM tariffs");
$latest_readings = $conn->query("
    SELECT u.user_id, u.full_name, u.meter_number, mr.reading_value, mr.reading_date 
    FROM users u
    LEFT JOIN (SELECT user_id, MAX(reading_date) as latest_date FROM meter_readings GROUP BY user_id) latest
        ON u.user_id = latest.user_id
    LEFT JOIN meter_readings mr 
        ON mr.user_id = latest.user_id AND mr.reading_date = latest.latest_date
    WHERE u.user_type = 'customer'
");
$pending_bills = [];
$customers_result = $conn->query("SELECT user_id FROM users WHERE user_type = 'customer'");
while ($customer = $customers_result->fetch_assoc()) {
    $user_id = $customer['user_id'];
    $bill = $conn->query("
        SELECT b.*, t.rate 
        FROM bills b
        JOIN tariffs t ON b.tariff_id = t.tariff_id
        WHERE b.user_id = $user_id AND b.status = 'pending'
        ORDER BY b.due_date ASC
        LIMIT 1
    ")->fetch_assoc();
    
    if ($bill) {
        $pending_bills[$user_id] = $bill;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EBMS - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
        
        .header-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .admin-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            animation: fadeIn 0.5s ease-out;
        }
        
        .admin-card h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--light);
        }
        
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background-color: rgba(92, 107, 192, 0.05);
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        textarea {
            min-height: 80px;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.2);
        }
        
        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        button:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(57, 73, 171, 0.3);
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            margin: 0 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            color: var(--secondary);
            transform: scale(1.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid {
            background-color: rgba(102, 187, 106, 0.2);
            color: var(--success);
        }
        
        .status-pending {
            background-color: rgba(255, 167, 38, 0.2);
            color: var(--warning);
        }
        
        .reminder-btn {
            background-color: var(--warning);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .reminder-btn:hover {
            background-color: #e69500;
        }
        
        .paid-btn {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .paid-btn:hover {
            background-color: #57a358;
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
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            table {
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
                    <span class="logo-text">EBMS Admin</span>
                </div>
                <div class="header-icons">
                    <button class="logout-btn" onclick="location.href='?logout=1'">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="admin-card">
            <h2><i class="fas fa-user-plus"></i> Create New Customer</h2>
            <form method="POST">
                <input type="hidden" name="create_customer" value="1">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Meter Number</label>
                    <input type="text" name="meter_number" required>
                </div>
                
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" required>
                </div>
                
                <button type="submit">Create Customer</button>
            </form>
        </div>
        <div class="admin-card">
            <h2><i class="fas fa-users-cog"></i> Customer Management</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Meter No.</th>
                        <th>Account No.</th>
                        <th>Last Reading</th>
                        <th>Bill Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers->fetch_assoc()): 
                        $reading = $latest_readings->fetch_assoc();
                        $user_id = $customer['user_id'];
                        $has_pending_bill = isset($pending_bills[$user_id]);
                    ?>
                    <tr>
                        <td><?= $customer['user_id'] ?></td>
                        <td><?= htmlspecialchars($customer['full_name']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= htmlspecialchars($customer['meter_number']) ?></td>
                        <td><?= htmlspecialchars($customer['account_number']) ?></td>
                        <td><?= $reading ? $reading['reading_value'] . ' kWh (' . $reading['reading_date'] . ')' : 'N/A' ?></td>
                        <td>
                            <?php if ($has_pending_bill): ?>
                                <span class="status-badge status-pending">Pending (₹<?= number_format($pending_bills[$user_id]['amount'], 2) ?>)</span>
                            <?php else: ?>
                                <span class="status-badge status-paid">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($has_pending_bill): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="update_payment_status" value="1">
                                    <input type="hidden" name="bill_id" value="<?= $pending_bills[$user_id]['bill_id'] ?>">
                                    <input type="hidden" name="status" value="paid">
                                    <button type="submit" class="paid-btn" title="Mark as Paid">
                                        <i class="fas fa-check"></i> Paid
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="send_reminder" value="1">
                                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                    <input type="hidden" name="bill_id" value="<?= $pending_bills[$user_id]['bill_id'] ?>">
                                    <button type="submit" class="reminder-btn" title="Send Reminder">
                                        <i class="fas fa-bell"></i> Remind
                                    </button>
                                </form>
                            <?php else: ?>
                                <span>No action needed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-card">
            <h2><i class="fas fa-file-invoice-dollar"></i> Tariff Management</h2>
            <form method="POST">
                <input type="hidden" name="update_tariff" value="1">
                <div class="form-group">
                    <label>Tariff Type</label>
                    <select name="tariff_type" required>
                        <?php 
                        $tariffs->data_seek(0);
                        while ($tariff = $tariffs->fetch_assoc()): ?>
                        <option value="<?= $tariff['tariff_type'] ?>">
                            <?= ucfirst($tariff['tariff_type']) ?> (Current: ₹<?= $tariff['rate'] ?>/kWh)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>New Rate (₹ per kWh)</label>
                    <input type="number" name="rate" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>Effective Date</label>
                    <input type="date" name="effective_date" required>
                </div>
                
                <button type="submit">Update Tariff</button>
            </form>
        </div>
        <div class="admin-card">
            <h2><i class="fas fa-tachometer-alt"></i> Submit Meter Reading</h2>
            <form method="POST">
                <input type="hidden" name="submit_reading" value="1">
                <div class="form-group">
                    <label>Customer</label>
                    <select name="user_id" required>
                        <?php 
                        $customers->data_seek(0);
                        while ($customer = $customers->fetch_assoc()): ?>
                        <option value="<?= $customer['user_id'] ?>">
                            <?= htmlspecialchars($customer['full_name']) ?> (Meter: <?= htmlspecialchars($customer['meter_number']) ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reading Date</label>
                    <input type="date" name="reading_date" required value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label>Reading Value (kWh)</label>
                    <input type="number" name="reading_value" required>
                </div>
                
                <button type="submit">Submit Reading</button>
            </form>
        </div>
    </main>

    <script>
        document.querySelector('[name="meter_number"]').addEventListener('input', function() {
            const accountField = document.querySelector('[name="account_number"]');
            if (!accountField.value && this.value.length >= 4) {
                accountField.value = 'CUST-' + this.value.slice(-4);
            }
        });
        document.querySelectorAll('.reminder-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to send a payment reminder to this customer?')) {
                    e.preventDefault();
                }
            });
        });
        document.querySelectorAll('.paid-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to mark this bill as paid?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>