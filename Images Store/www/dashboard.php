<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Images Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            margin: auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
        }

        .welcome-box {
            background: #f8f9ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .welcome-box h2 {
            color: #667eea;
            margin-bottom: 5px;
        }

        .welcome-box p {
            color: #666;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 20px;
            width: 100%;
            margin-top: auto;
        }

        footer h1 {
            font-size: 28px;
            font-weight: 700;
        }

        footer h1 span {
            color: #ffd700;
        }

        header {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: auto;
        }

        .session-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5ea;
            font-size: 12px;
            color: #999;
            text-align: center;
        }

        .user-avatar {
            font-size: 60px;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>
<body>
    <footer>
        <h1>👋 Welcome to Our <span>Images Store</span></h1>
    </footer>

    <div class="container">
        <div class="header">
            <h1>📊 Dashboard</h1>
            <p>You are successfully logged in</p>
        </div>

        <div class="welcome-box">
            <span class="user-avatar">👤</span>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h2>
            <p style="margin-top: 10px; font-size: 12px; color: #999;">
                Logged in since: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()); ?>
            </p>
        </div>

        <div class="action-buttons">
            <a href="upload.php" class="btn btn-primary">📸 Upload Image</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>

    </div>

    <header>
        &copy; <strong>Arab Security Cyber Wargames</strong> 2026
    </header>
</body>
</html>