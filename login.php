<?php
// File: login.php
require_once 'controllers/AuthController.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (AuthController::login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #333;
            --card-bg: #fff;
            --accent: #3f51b5;
        }
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #f5f5f5;
            --card-bg: #1e1e1e;
            --accent: #7986cb;
        }
        body {
            margin:0;
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:'Roboto', sans-serif;
            background:var(--bg-color);
            color:var(--text-color);
        }
        .login-box {
            background:var(--card-bg);
            padding:40px;
            border-radius:12px;
            box-shadow:0 6px 16px rgba(0,0,0,0.25);
            width:360px;
            text-align:center;
            animation: fadeIn 0.4s ease;
        }
        .login-box h2 {
            margin-bottom:25px;
            color:var(--accent);
        }
        .form-group {
            position:relative;
            margin-bottom:20px;
        }
        .form-group input {
            width:fit-content;
            padding:12px 40px 12px 12px;
            border:1px solid #ccc;
            border-radius:6px;
            outline:none;
            background:transparent;
            color:var(--text-color);
            font-size:15px;
        }
        .form-group input:focus {
            border-color:var(--accent);
        }
        .form-group i {
            position:absolute;
            right:12px;
            top:50%;
            transform:translateY(-50%);
            color:#888;
        }
        button {
            width:100%;
            padding:12px;
            background:var(--accent);
            color:white;
            border:none;
            border-radius:6px;
            font-size:16px;
            cursor:pointer;
            transition:all 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow:0 4px 10px rgba(0,0,0,0.2);
        }
        .error {
            color:#f44336;
            margin-top:15px;
        }
        .theme-toggle {
            position:absolute;
            top:20px;
            right:20px;
            cursor:pointer;
            color:var(--text-color);
            font-size:20px;
            transition:0.2s;
        }
        .theme-toggle:hover { opacity:0.7; }
        @keyframes fadeIn {
            from { opacity:0; transform: translateY(-10px);}
            to { opacity:1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </div>

    <div class="login-box">
        <h2><i class="fas fa-user-shield"></i> Admin Login</h2>
        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fas fa-envelope"></i>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fas fa-lock"></i>
            </div>
            <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.querySelector('.theme-toggle i');
            if (html.getAttribute('data-theme') === 'dark') {
                html.removeAttribute('data-theme');
                icon.classList.replace('fa-sun','fa-moon');
            } else {
                html.setAttribute('data-theme','dark');
                icon.classList.replace('fa-moon','fa-sun');
            }
        }
    </script>
</body>
</html>
