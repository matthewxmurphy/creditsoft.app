<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Credit Error Identifier System' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            /* Light Theme */
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --accent-color: #4f46e5;
            --accent-hover: #4338ca;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --rail-width: 70px;
            --top-rail-height: 60px;
            --content-padding: 20px;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --accent-color: #818cf8;
            --accent-hover: #6366f1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Left Rail */
        .left-rail {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--rail-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: width 0.3s ease;
        }

        .left-rail:hover {
            width: 220px;
        }

        .left-rail-logo {
            height: var(--top-rail-height);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
            overflow: hidden;
        }

        .left-rail-logo i {
            font-size: 24px;
            color: var(--accent-color);
        }

        .left-rail-logo span {
            display: none;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
            white-space: nowrap;
            margin-left: 10px;
        }

        .left-rail:hover .left-rail-logo span {
            display: block;
        }

        .left-rail-nav {
            flex: 1;
            padding: 10px 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .rail-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            justify-content: center;
            cursor: pointer;
        }

        .left-rail:hover .rail-item {
            justify-content: flex-start;
            padding-left: 20px;
        }

        .rail-item i {
            font-size: 20px;
            width: 30px;
            text-align: center;
        }

        .rail-item span {
            display: none;
            white-space: nowrap;
            margin-left: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .left-rail:hover .rail-item span {
            display: block;
        }

        .rail-item:hover, .rail-item.active {
            color: var(--accent-color);
            background: rgba(79, 70, 229, 0.1);
        }

        .rail-item .badge {
            display: none;
            margin-left: auto;
            font-size: 10px;
            padding: 2px 6px;
        }

        .left-rail:hover .rail-item .badge {
            display: block;
        }

        /* Top Rail */
        .top-rail {
            position: fixed;
            top: 0;
            left: var(--rail-width);
            right: 0;
            height: var(--top-rail-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 999;
        }

        .top-rail-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-rail-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-add-client {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-add-client:hover {
            background: var(--accent-hover);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px 8px 36px;
            font-size: 14px;
            width: 250px;
            color: var(--text-primary);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .user-menu:hover {
            background: var(--bg-tertiary);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--accent-color);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--rail-width);
            margin-top: var(--top-rail-height);
            padding: var(--content-padding);
            min-height: calc(100vh - var(--top-rail-height));
        }

        /* Cards */
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Stats Cards */
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .stat-icon.primary { background: rgba(79, 70, 229, 0.1); color: var(--accent-color); }
        .stat-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info-color); }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* Tables */
        .table {
            color: var(--text-primary);
        }

        .table thead th {
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
        }

        .table td {
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        /* Badges */
        .badge-severity-critical { background: var(--danger-color); color: white; }
        .badge-severity-high { background: #f97316; color: white; }
        .badge-severity-medium { background: var(--warning-color); color: black; }
        .badge-severity-low { background: var(--text-secondary); color: white; }

        .badge-status-active { background: var(--success-color); color: white; }
        .badge-status-inactive { background: var(--text-secondary); color: white; }
        .badge-status-prospect { background: var(--info-color); color: white; }

        /* Buttons */
        .btn-primary {
            background: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }

        .btn-outline-primary {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-outline-primary:hover {
            background: var(--accent-color);
            color: white;
        }

        /* Forms */
        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--accent-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        /* Alerts */
        .alert-success { background: rgba(16, 185, 129, 0.1); border-color: var(--success-color); color: var(--success-color); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border-color: var(--danger-color); color: var(--danger-color); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border-color: var(--warning-color); color: var(--warning-color); }
        .alert-info { background: rgba(59, 130, 246, 0.1); border-color: var(--info-color); color: var(--info-color); }

        /* Modals */
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        /* Dropdown */
        .dropdown-menu {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dropdown-item {
            color: var(--text-primary);
        }

        .dropdown-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .left-rail {
                width: 70px;
            }
            
            .left-rail:hover {
                width: 70px;
            }
            
            .left-rail-logo span,
            .rail-item span,
            .rail-item .badge {
                display: none;
            }
            
            .left-rail:hover .left-rail-logo span,
            .left-rail:hover .rail-item span,
            .left-rail:hover .rail-item .badge {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .top-rail {
                left: 70px;
            }
            
            .search-box input {
                width: 150px;
            }
            
            .user-info {
                display: none;
            }
        }
    </style>
    
    <script>
        // Theme toggle functionality
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            
            // Save preference
            fetch('/settings/update-theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: newTheme })
            });
        }
    </script>
</head>
<body data-theme="<?= $theme ?? 'light' ?>">
