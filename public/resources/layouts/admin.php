<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin - {{ app_name }}')</title>
    <meta name="description" content="@yield('description', 'Admin Panel - {{ app_name }}')">
    <meta name="csrf-token" content="{{ csrf_token }}">
    
    @yield('head')
    
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .admin-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; }
        .main-content { flex: 1; padding: 20px; }
        .admin-header { background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-content { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin: 10px 0; }
        .sidebar a { color: white; text-decoration: none; }
        .sidebar a:hover { color: #3498db; }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <ul>
                    <li><a href="/admin">Dashboard</a></li>
                    <li><a href="/admin/users">Users</a></li>
                    <li><a href="/admin/settings">Settings</a></li>
                </ul>
            </nav>
            @yield('sidebar')
        </aside>
        
        <main class="main-content">
            <header class="admin-header">
                <h1>@yield('header', 'Admin Dashboard')</h1>
                @yield('admin_navigation')
            </header>
            
            <div class="admin-content">
                @yield('content')
            </div>
        </main>
    </div>
    
    @yield('scripts')
</body>
</html>