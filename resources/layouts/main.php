<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '{{ app_name }}')</title>
    <meta name="description" content="@yield('description', '{{ app_name }} - Modern PHP Framework')">
    <meta name="csrf-token" content="{{ csrf_token }}">
    
    @yield('head')
    
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { text-align: center; margin-top: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>@yield('header', '{{ app_name }}')</h1>
            @yield('navigation')
        </header>
        
        <main class="content">
            @yield('content')
        </main>
        
        <footer class="footer">
            @yield('footer', '<p>&copy; {{ current_time }} {{ app_name }} v{{ app_version }}</p>')
        </footer>
    </div>
    
    @yield('scripts')
</body>
</html>