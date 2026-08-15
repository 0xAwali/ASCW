<?php
session_start();

$VALID_USERNAME = 'admin';
$VALID_PASSWORD = '!2O26!^@%^*ASCWG!%';

$login_error = '';
$username = '';

function generateCSRFToken() {
    return bin2hex(random_bytes(32));
}

function getRealClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

$loginAttemptsFile = '/var/www/data/login_attempts.json';
$rateLimitKey = getRealClientIp();

$LOGIN_TEMP_LOCK_THRESHOLD = 5;
$LOGIN_TEMP_LOCK_DURATION  = 3600;
$LOGIN_PERMANENT_THRESHOLD = 1000;

function loadAttempts(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    $contents = file_get_contents($file);
    $data = json_decode($contents, true);
    return is_array($data) ? $data : [];
}

function checkLockoutStatus(array $data, string $key): array {
    if (!isset($data[$key]) || !is_array($data[$key])) {
        return ['status' => 'ok', 'retry_after' => null];
    }
    $entry = $data[$key];
    if (!empty($entry['permanent'])) {
        return ['status' => 'permanent', 'retry_after' => null];
    }
    $lockUntil = $entry['lock_until'] ?? null;
    if ($lockUntil !== null && time() < $lockUntil) {
        return ['status' => 'locked', 'retry_after' => $lockUntil - time()];
    }
    return ['status' => 'ok', 'retry_after' => null];
}

function recordFailedAttempt(string $file, string $key, int $tempThreshold, int $tempDuration, int $permThreshold): void {
    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $contents = $size > 0 ? fread($fp, $size) : '';
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            $data = [];
        }
        $entry = $data[$key] ?? ['total_count' => 0, 'window_count' => 0, 'lock_until' => null, 'permanent' => false];
        $now = time();

        if (!empty($entry['lock_until']) && $now >= $entry['lock_until']) {
            $entry['lock_until'] = null;
            $entry['window_count'] = 0;
        }

        if (empty($entry['permanent'])) {
            $entry['total_count'] = ($entry['total_count'] ?? 0) + 1;
            $entry['window_count'] = ($entry['window_count'] ?? 0) + 1;

            if ($entry['total_count'] >= $permThreshold) {
                $entry['permanent'] = true;
                $entry['lock_until'] = null;
            } elseif ($entry['window_count'] >= $tempThreshold) {
                $entry['lock_until'] = $now + $tempDuration;
            }
        }

        $data[$key] = $entry;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function resetAttempts(string $file, string $key): void {
    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $contents = $size > 0 ? fread($fp, $size) : '';
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            $data = [];
        }
        unset($data[$key]);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = generateCSRFToken();
    $_SESSION['csrf_token_used'] = false;
    if (!isset($_SESSION['login_attempt'])) {
        $_SESSION['login_attempt'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || 
        !isset($_SESSION['csrf_token']) || 
        $_SESSION['csrf_token_used'] === true ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        $login_error = 'Security token validation failed. Please refresh and try again.';
        goto display_page;
    }
    $_SESSION['csrf_token_used'] = true;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (empty($username) || empty($password)) {
        $login_error = 'Please enter both username and password.';
        goto display_page;
    }

    $lockStatus = checkLockoutStatus(loadAttempts($loginAttemptsFile), $rateLimitKey);
    if ($lockStatus['status'] === 'permanent') {
        $login_error = 'This IP Locked';
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        goto display_page;
    }
    if ($lockStatus['status'] === 'locked') {
        $minutesLeft = (int) ceil($lockStatus['retry_after'] / 60);
        $login_error = "Please try again in about {$minutesLeft} minute";
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        goto display_page;
    }

    $_SESSION['login_attempt'] = isset($_SESSION['login_attempt']) ? $_SESSION['login_attempt'] + 1 : 1;
    if ($_SESSION['login_attempt'] > 5) {
        $login_error = 'Too many login attempts. Please try again later.';
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        goto display_page;
    }
    
    if ($username === $VALID_USERNAME && $password === $VALID_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['login_attempt'] = 0;
        resetAttempts($loginAttemptsFile, $rateLimitKey);
        session_regenerate_id(true);
        
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
        
        header('Location: dashboard.php');
        exit;
    } else {
        recordFailedAttempt($loginAttemptsFile, $rateLimitKey, $LOGIN_TEMP_LOCK_THRESHOLD, $LOGIN_TEMP_LOCK_DURATION, $LOGIN_PERMANENT_THRESHOLD);
        $login_error = 'Invalid username or password.';
        $_SESSION['csrf_token'] = generateCSRFToken();
        $_SESSION['csrf_token_used'] = false;
    }
}

display_page:
if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token_used'] === true) {
    $_SESSION['csrf_token'] = generateCSRFToken();
    $_SESSION['csrf_token_used'] = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Images Store</title>
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
            padding: 0;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 450px;
            width: 100%;
            margin: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
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
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ea;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #f8f9ff;
            color: #333;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        .login-btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: auto;
        }

        footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 20px;
            width: 100%;
        }

        footer h1 {
            font-size: 32px;
            font-weight: 700;
            text-transform: capitalize;
            letter-spacing: 1px;
        }

        footer h1 span {
            color: #ffd700;
        }

        .message-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .message-box.show {
            display: block;
        }

        .message-box.error {
            background: #f8d7da;
            border: 2px solid #f5453d;
            color: #721c24;
        }

        .message-box.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }

        .message-box p {
            margin: 0;
            font-weight: 600;
            font-size: 14px;
            line-height: 1.6;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5ea;
            color: #666;
            font-size: 13px;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .lock-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .input-icon-wrapper {
            position: relative;
        }

        .input-icon-wrapper .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 18px;
        }

        .input-icon-wrapper input {
            padding-left: 45px;
        }

        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .show-password:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <footer>
        <h1>👋 Welcome to Our <span>Images Store</span></h1>
    </footer>

    <div class="container">
        <?php if ($login_error): ?>
        <div class="message-box error show">
            <p>⚠️ <?php echo htmlspecialchars($login_error); ?></p>
        </div>
        <?php endif; ?>

        <div class="header">
            <span class="lock-icon">🔐</span>
            <h1>Login</h1>
            <p>Enter your credentials to access the image store</p>
        </div>

        <form method="post" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-icon-wrapper">
                    <span class="icon">👤</span>
                    <input type="text" id="username" name="username" 
                           placeholder="admin" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon-wrapper">
                    <span class="icon">🔑</span>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter admin password" required>
                    <button type="button" class="show-password" id="togglePassword">Show</button>
                </div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
        </form>

        <div class="login-footer">
            Having trouble signing in? <a href="contact.php">Contact Us</a>
        </div>

    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.textContent = type === 'password' ? 'Show' : 'Hide';
        });

        const loginForm = document.getElementById('loginForm');
        const usernameInput = document.getElementById('username');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function(e) {
            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();

            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return;
            }

            loginBtn.disabled = true;
            loginBtn.textContent = 'Signing in...';
        });

        window.addEventListener('load', function() {
            if (!usernameInput.value) {
                usernameInput.focus();
            }
        });

        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password.');
                }
            }
        });
    </script>

    <header>
        &copy; <strong>Arab Security Cyber Wargames</strong> 2026
    </header>
</body>
</html>