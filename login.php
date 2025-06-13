<?php
session_start();
require_once 'db_connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];

            if ($_SESSION['user_type'] !== 'admin') {
                header("Location: index.php");
                exit;
            } else {
                
                header("Location: admin_index.php");
                exit;
            }
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>EBMS Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            background-image: url('https://group.met.com/media/omvoe0f3/electricity.jpg?anchor=center&mode=crop&width=1920&rnd=133293326643000000&mode=max&upscale=false');
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            position: relative;
    padding: 2rem;
    border-radius: 10px;
    overflow: visible;
    z-index: 1;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img {
            height: 60px;
            margin-bottom: 1rem;
        }
        .login-header h2 {
            color: #98a1d8;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .login-btn {
            background-color: #98a1d8;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 6px;
            font-size: 1rem;
            width: 100%;
            cursor: pointer;
        }
        .error-message {
            color: #ef5350;
            margin-bottom: 1rem;
            text-align: center;
        }
        .login-container::after {
    content: '';
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    pointer-events: none;
}
.glider {
    position: absolute;
    width: 120px;
    height: 10px;
    background: linear-gradient(90deg, 
        #ff0000, #ff7300, #fffb00, #48ff00, 
        #00ffd5, #002bff, #7a00ff, #ff00c8);
    background-size: 800%;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    animation: circle-round 6s linear infinite;
    z-index: -1;
    transform-origin: 20px 5px;
}

@keyframes circle-round {
    0% {
        top: -15px;
        left: 0%;
        transform: rotate(0deg);
        background-position: 0% 50%;
    }
    25% {
        top: -15px;
        left: 100%;
        transform: rotate(0deg);
        background-position: 100% 50%;
    }
    26% {
        transform: rotate(90deg);
    }
    50% {
        top: calc(100% - 5px);
        left: 100%;
        transform: rotate(90deg);
        background-position: 200% 50%;
    }
    51% {
        transform: rotate(180deg);
    }
    75% {
        top: calc(100% - 5px);
        left: -15px;
        transform: rotate(180deg);
        background-position: 300% 50%;
    }
    76% {
        transform: rotate(270deg);
    }
    100% {
        top: -15px;
        left: 0%;
        transform: rotate(270deg);
        background-position: 400% 50%;
    }
}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="logo.png" alt="EBMS Logo">
            <h2>EBMS Login</h2>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-btn">Login</button>
        </form>
        <div class="glider"></div>
    </div>
</body>
</html>