<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Login - Credit Error Identifier System' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #f8f9fa;
            --text-primary: #212529;
            --accent-color: #4f46e5;
            --accent-hover: #4338ca;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --text-primary: #f1f5f9;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }

        [data-theme="dark"] .login-card {
            background: #1e293b;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--accent-color), #818cf8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: white;
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 14px;
        }

        .form-label {
            font-weight: 500;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-control {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            font-size: 14px;
        }

        [data-theme="dark"] .form-control {
            background: #334155;
            border-color: #475569;
            color: white;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: var(--accent-color);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #dee2e6;
        }

        .login-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
        }
    </style>
</head>
<body data-theme="light">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="bi bi-credit-card-2-front"></i>
                </div>
                <h1 class="login-title">Credit System</h1>
                <p class="login-subtitle">Error Identifier & Credit Management</p>
            </div>

            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= session()->getFlashdata('error') ?>
            </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= session()->getFlashdata('success') ?>
            </div>
            <?php endif; ?>

            <form action="/auth/doLogin" method="POST">
                <?= csrf_field() ?>
                
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <p class="text-muted mb-0">
                    Default Login:<br>
                    <strong>admin@creditsystem.local</strong> / <strong>admin123</strong>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
