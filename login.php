<?php
require_once __DIR__ . '/auth.php';

$error = '';
$accounts = get_auth_accounts();

if (current_user()) {
    $role = current_user()['role'];
    header('Location: ' . ($role === 'admin' ? 'admin.php' : 'counter.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    if (isset($accounts[$username]) && $accounts[$username]['password'] === $password) {
        login_user([
            'username' => $username,
            'role' => $accounts[$username]['role'],
            'name' => $accounts[$username]['name'],
            'display_name' => $accounts[$username]['display_name'],
        ]);

        header('Location: ' . ($accounts[$username]['role'] === 'admin' ? 'admin.php' : 'counter.php'));
        exit();
    }

    $error = 'Invalid username or password.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Login</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.25);
            padding: 34px;
        }

        .login-card h1 {
            margin: 0 0 8px;
            color: #1f2937;
            font-size: 30px;
        }

        .login-card p {
            margin: 0 0 24px;
            color: #6b7280;
            line-height: 1.5;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #374151;
        }

        input, select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .submit-btn {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.28);
        }

        .demo-box {
            margin-top: 22px;
            padding: 14px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
        }

        .demo-box strong {
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Restaurant Login</h1>
        <p>Sign in as Admin to manage menu items, or as Counter Staff to view orders and total revenue.</p>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
            <div class="error">You do not have permission to open that page. Please sign in with the correct role.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select role</option>
                    <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="counter" <?php echo (($_POST['role'] ?? '') === 'counter') ? 'selected' : ''; ?>>Counter Staff</option>
                </select>
            </div>

            <button type="submit" class="submit-btn">Log In</button>
        </form>

        <div class="demo-box">
            <strong>Demo accounts:</strong><br>
            Admin: <strong>admin / admin123</strong><br>
            Counter Staff: <strong>counter / counter123</strong>
            <br><span style="display:block;margin-top:8px;">The role is detected from the account, so the dropdown is only for reference.</span>
        </div>
    </div>
</body>
</html>