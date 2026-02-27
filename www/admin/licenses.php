<?php
$page_title = 'Admin - Licenses';
$page_description = 'License Management';
$page_hero = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#2563eb;--dark:#0f172a;--light:#f8fafc;--gray:#64748b;--border:#e2e8f0;--success:#10b981;--danger:#ef4444;--warning:#f59e0b}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        .header{background:var(--dark);color:white;padding:20px 40px;display:flex;justify-content:space-between;align-items:center}
        .header h1{font-size:24px}
        .header a{color:white;text-decoration:none;opacity:0.8}
        .container{max-width:1400px;margin:0 auto;padding:20px}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
        .stat-card{background:white;padding:20px;border-radius:12px;border:1px solid var(--border)}
        .stat-card .value{font-size:32px;font-weight:700;color:var(--primary)}
        .stat-card .label{font-size:14px;color:var(--gray)}
        .card{background:white;border-radius:12px;border:1px solid var(--border);overflow:hidden}
        .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
        .card-header h2{font-size:18px}
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none}
        .btn-primary{background:var(--primary);color:white}
        .btn-danger{background:var(--danger);color:white}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 16px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--light);font-weight:600;font-size:13px;text-transform:uppercase}
        tr:hover{background:var(--light)}
        .status{ padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status.active{background:#d1fae5;color:#065f46}
        .status.expired{background:#fee2e2;color:#991b1b}
        .status.grace{background:#fef3c7;color:#92400e}
        .badge{ padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.starter{background:#e0e7ff;color:#3730a3}
        .badge.professional{background:#dbeafe;color:#1e40af}
        .badge.enterprise{background:#fce7f3;color:#9d174d}
        .actions{display:flex;gap:8px}
        .actions button{font-size:12px;padding:6px 12px}
        .log-entry{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px}
        .log-entry .time{color:var(--gray);font-size:12px}
        #licensesTable{margin-bottom:24px}
        .tab-content{display:none}
        .tab-content.active{display:block}
        .tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--border)}
        .tab{padding:12px 24px;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px}
        .tab.active{border-bottom-color:var(--primary);color:var(--primary);font-weight:600}
    </style>
</head>
<body>
    <div class="header">
        <h1>CreditSoft Admin</h1>
        <div>
            <a href="/">← Back to Site</a>
        </div>
    </div>
    
    <div class="container">
        <div class="tabs">
            <div class="tab active" onclick="showTab('licenses')">Licenses</div>
            <div class="tab" onclick="showTab('logs')">Activity Logs</div>
            <div class="tab" onclick="showTab('grace')">Grace Abuse</div>
            <div class="tab" onclick="showTab('create')">Create License</div>
        </div>
        
        <div id="licenses" class="tab-content active">
            <div class="stats" id="stats">
                <div class="stat-card"><div class="value" id="totalLicenses">-</div><div class="label">Total Licenses</div></div>
                <div class="stat-card"><div class="value" id="activeLicenses">-</div><div class="label">Active</div></div>
                <div class="stat-card"><div class="value" id="expiringSoon">-</div><div class="label">Expiring Soon</div></div>
                <div class="stat-card"><div class="value" id="inGrace">-</div><div class="label">In Grace Period</div></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>All Licenses</h2>
                    <button class="btn btn-primary" onclick="refreshLicenses()">Refresh</button>
                </div>
                <table id="licensesTable">
                    <thead>
                        <tr>
                            <th>License Key</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Last Validated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="licensesBody">
                        <tr><td colspan="7" style="text-align:center;padding:40px;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="logs" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Activity Logs</h2>
                </div>
                <div id="logsBody">
                    <div style="padding:40px;text-align:center;color:var(--gray);">Connect database to view logs</div>
                </div>
            </div>
        </div>
        
        <div id="grace" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Grace Period Abuse Detection</h2>
                </div>
                <div id="graceBody">
                    <div style="padding:40px;text-align:center;color:var(--gray);">Connect database to view grace period tracking</div>
                </div>
            </div>
        </div>
        
        <div id="create" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Create New License</h2>
                </div>
                <div style="padding:20px">
                    <div style="max-width:400px">
                        <label style="display:block;margin-bottom:8px;font-weight:500;">Email</label>
                        <input type="email" id="newEmail" placeholder="customer@example.com" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;margin-bottom:16px">
                        
                        <label style="display:block;margin-bottom:8px;font-weight:500;">Plan</label>
                        <select id="newPlan" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;margin-bottom:16px">
                            <option value="starter">Starter ($49/mo)</option>
                            <option value="professional" selected>Professional ($99/mo)</option>
                            <option value="enterprise">Enterprise ($199/mo)</option>
                        </select>
                        
                        <label style="display:block;margin-bottom:8px;font-weight:500;">Duration</label>
                        <select id="newDuration" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;margin-bottom:16px">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                        
                        <button class="btn btn-primary" onclick="createLicense()" style="width:100%">Create License</button>
                        
                        <div id="createResult" style="margin-top:16px"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById(tab).classList.add('active');
        if(tab === 'licenses') loadLicenses();
    }
    
    async function refreshLicenses() {
        await loadLicenses();
    }
    
    async function loadLicenses() {
        try {
            const res = await fetch('/api/license.php?action=admin-list');
            const data = await res.json();
            
            if(data.error) {
                document.getElementById('licensesBody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;">No database connection</td></tr>';
                return;
            }
            
            const licenses = data.licenses || [];
            document.getElementById('totalLicenses').textContent = licenses.length;
            document.getElementById('activeLicenses').textContent = licenses.filter(l => l.status === 'active').length;
            
            const now = new Date();
            const soon = licenses.filter(l => {
                const exp = new Date(l.expires_at);
                const diff = (exp - now) / (1000*60*60*24);
                return diff > 0 && diff < 7;
            });
            document.getElementById('expiringSoon').textContent = soon.length;
            document.getElementById('inGrace').textContent = '0';
            
            let html = '';
            licenses.forEach(l => {
                const exp = new Date(l.expires_at);
                const diff = (exp - now) / (1000*60*60*24);
                let status = '<span class="status active">Active</span>';
                if(diff < 0) status = '<span class="status expired">Expired</span>';
                else if(diff < 4) status = '<span class="status grace">Grace</span>';
                
                const planBadge = '<span class="badge ' + l.plan + '">' + (l.plan || 'professional') + '</span>';
                
                html += '<tr>';
                html += '<td style="font-family:monospace;font-size:12px;">' + (l.license_key || 'N/A') + '</td>';
                html += '<td>' + (l.customer_email || 'N/A') + '</td>';
                html += '<td>' + planBadge + '</td>';
                html += '<td>' + status + '</td>';
                html += '<td>' + (l.expires_at ? new Date(l.expires_at).toLocaleDateString() : 'Never') + '</td>';
                html += '<td>' + (l.last_validated ? new Date(l.last_validated).toLocaleDateString() : 'Never') + '</td>';
                html += '<td class="actions">';
                html += '<button class="btn btn-primary" onclick="viewLicense(' + l.id + ')">View</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            if(!html) html = '<tr><td colspan="7" style="text-align:center;padding:40px;">No licenses found</td></tr>';
            document.getElementById('licensesBody').innerHTML = html;
            
        } catch(e) {
            document.getElementById('licensesBody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;">Error loading licenses</td></tr>';
        }
    }
    
    async function createLicense() {
        const email = document.getElementById('newEmail').value;
        const plan = document.getElementById('newPlan').value;
        const duration = document.getElementById('newDuration').value;
        
        if(!email) {
            document.getElementById('createResult').innerHTML = '<div style="color:var(--danger)">Please enter an email</div>';
            return;
        }
        
        try {
            const res = await fetch('/api/license.php?action=create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email, plan, duration})
            });
            const data = await res.json();
            
            if(data.license_key) {
                document.getElementById('createResult').innerHTML = '<div style="background:#d1fae5;color:#065f46;padding:12px;border-radius:8px;"><strong>License Created!</strong><br>Key: ' + data.license_key + '<br>Expires: ' + new Date(data.expires_at).toLocaleDateString() + '</div>';
                loadLicenses();
            } else {
                document.getElementById('createResult').innerHTML = '<div style="color:var(--danger)">Error: ' + (data.error || 'Unknown error') + '</div>';
            }
        } catch(e) {
            document.getElementById('createResult').innerHTML = '<div style="color:var(--danger)">Connection error</div>';
        }
    }
    
    function viewLicense(id) {
        alert('License details for ID: ' + id);
    }
    
    loadLicenses();
    </script>
</body>
</html>
