<?php
$page_title = 'Admin Login';
$page_hero = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#2563eb;--dark:#0f172a;--light:#f8fafc;--gray:#64748b;--border:#e2e8f0;--danger:#ef4444}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--light);color:var(--dark);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-card{background:white;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:100%;max-width:400px}
        .login-card h1{font-size:24px;margin-bottom:8px;text-align:center}
        .login-card p{color:var(--gray);text-align:center;margin-bottom:24px}
        .login-card input{width:100%;padding:14px;border:2px solid var(--border);border-radius:10px;font-size:16px;margin-bottom:16px}
        .login-card button{width:100%;padding:14px;background:var(--primary);color:white;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer}
        .login-card button:hover{background:#1d4ed8}
        .error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px;text-align:center;display:none}
        .logo{text-align:center;margin-bottom:24px}
        .logo img{height:60px}
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <img src="/assets/images/CreditSoft.png" alt="CreditSoft">
        </div>
        <h1>Admin Login</h1>
        <p>Enter your admin credentials</p>
        
        <div class="error" id="error"></div>
        
        <input type="email" id="email" placeholder="Admin Email">
        <input type="password" id="password" placeholder="Password">
        <button onclick="doLogin()">Login</button>
    </div>
    
    <script>
    async function doLogin() {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const error = document.getElementById('error');
        
        if(!email || !password) {
            error.textContent = 'Please enter email and password';
            error.style.display = 'block';
            return;
        }
        
        error.style.display = 'none';
        
        try {
            const res = await fetch('/api/admin-login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email, password})
            });
            const data = await res.json();
            
            if(data.success) {
                localStorage.setItem('admin_token', data.token);
                window.location.href = '/admin/licenses';
            } else {
                error.textContent = data.error || 'Invalid credentials';
                error.style.display = 'block';
            }
        } catch(e) {
            error.textContent = 'Connection error';
            error.style.display = 'block';
        }
    }
    </script>
</body>
</html>
