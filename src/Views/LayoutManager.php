<?php

namespace Tero\Views;

use Tero\Config\ConfigManager;
use Tero\Templates\TemplateEngine;

/**
 * LayoutManager - Gestor de layouts y bloques
 * 
 * @package Tero\Views
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class LayoutManager
{
    private ConfigManager $config;
    private TemplateEngine $templateEngine;
    private string $layoutPath;
    private array $layouts = [];
    private array $blocks = [];
    private string $currentLayout = '';
    private array $stack = [];

    public function __construct(ConfigManager $config, TemplateEngine $templateEngine)
    {
        $this->config = $config;
        $this->templateEngine = $templateEngine;
        $this->layoutPath = $config->get('LAYOUT_PATH', 'resources/layouts');
        
        $this->ensureLayoutDirectory();
        $this->loadDefaultLayouts();
    }

    /**
     * Asegurar que el directorio de layouts existe
     */
    private function ensureLayoutDirectory(): void
    {
        if (!is_dir($this->layoutPath)) {
            mkdir($this->layoutPath, 0755, true);
        }
    }

    /**
     * Cargar layouts por defecto
     */
    private function loadDefaultLayouts(): void
    {
        $this->createDefaultLayouts();
    }

    /**
     * Crear layouts por defecto
     */
    private function createDefaultLayouts(): void
    {
        // Layout principal
        $mainLayout = $this->layoutPath . '/main.php';
        if (!file_exists($mainLayout)) {
            $content = $this->getDefaultMainLayout();
            file_put_contents($mainLayout, $content);
        }

        // Layout de admin
        $adminLayout = $this->layoutPath . '/admin.php';
        if (!file_exists($adminLayout)) {
            $content = $this->getDefaultAdminLayout();
            file_put_contents($adminLayout, $content);
        }

        // Layout de API
        $apiLayout = $this->layoutPath . '/api.php';
        if (!file_exists($apiLayout)) {
            $content = $this->getDefaultApiLayout();
            file_put_contents($apiLayout, $content);
        }
    }

    /**
     * Obtener layout principal por defecto
     */
    private function getDefaultMainLayout(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield(\'title\', \'{{ app_name }}\')</title>
    <meta name="description" content="@yield(\'description\', \'{{ app_name }} - Modern PHP Framework\')">
    <meta name="csrf-token" content="{{ csrf_token }}">
    
    @yield(\'head\')
    
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
            <h1>@yield(\'header\', \'{{ app_name }}\')</h1>
            @yield(\'navigation\')
        </header>
        
        <main class="content">
            @yield(\'content\')
        </main>
        
        <footer class="footer">
            @yield(\'footer\', \'<p>&copy; {{ current_time }} {{ app_name }} v{{ app_version }}</p>\')
        </footer>
    </div>
    
    @yield(\'scripts\')
</body>
</html>';
    }

    /**
     * Obtener layout de admin por defecto
     */
    private function getDefaultAdminLayout(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield(\'title\', \'Admin - {{ app_name }}\')</title>
    <meta name="description" content="@yield(\'description\', \'Admin Panel - {{ app_name }}\')">
    <meta name="csrf-token" content="{{ csrf_token }}">
    
    @yield(\'head\')
    
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
            @yield(\'sidebar\')
        </aside>
        
        <main class="main-content">
            <header class="admin-header">
                <h1>@yield(\'header\', \'Admin Dashboard\')</h1>
                @yield(\'admin_navigation\')
            </header>
            
            <div class="admin-content">
                @yield(\'content\')
            </div>
        </main>
    </div>
    
    @yield(\'scripts\')
</body>
</html>';
    }

    /**
     * Obtener layout de API por defecto
     */
    private function getDefaultApiLayout(): string
    {
        return '<?php
header(\'Content-Type: application/json\');
header(\'Access-Control-Allow-Origin: *\');
header(\'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS\');
header(\'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With\');

if ($_SERVER[\'REQUEST_METHOD\'] === \'OPTIONS\') {
    http_response_code(200);
    exit;
}

@yield(\'content\')';
    }

    /**
     * Establecer layout actual
     */
    public function setLayout(string $layout): void
    {
        $this->currentLayout = $layout;
    }

    /**
     * Obtener layout actual
     */
    public function getCurrentLayout(): string
    {
        return $this->currentLayout;
    }

    /**
     * Renderizar layout
     */
    public function renderLayout(string $layout, array $data = []): string
    {
        $layoutFile = $this->layoutPath . '/' . ltrim($layout, '/') . '.php';
        
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        
        return $this->templateEngine->render($layout, $data);
    }

    /**
     * Iniciar bloque
     */
    public function startBlock(string $name): void
    {
        $this->stack[] = $name;
        ob_start();
    }

    /**
     * Finalizar bloque
     */
    public function endBlock(): void
    {
        if (empty($this->stack)) {
            throw new \RuntimeException("No block to end");
        }
        
        $name = array_pop($this->stack);
        $this->blocks[$name] = ob_get_clean();
    }

    /**
     * Obtener bloque
     */
    public function getBlock(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    /**
     * Verificar si bloque existe
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    /**
     * Limpiar bloques
     */
    public function clearBlocks(): void
    {
        $this->blocks = [];
        $this->stack = [];
    }

    /**
     * Obtener todos los bloques
     */
    public function getAllBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Obtener layouts disponibles
     */
    public function getAvailableLayouts(): array
    {
        $layouts = [];
        $files = glob($this->layoutPath . '/*.php');
        
        foreach ($files as $file) {
            $layoutName = basename($file, '.php');
            $layouts[] = $layoutName;
        }
        
        return $layouts;
    }

    /**
     * Crear layout personalizado
     */
    public function createLayout(string $name, string $content): void
    {
        $layoutFile = $this->layoutPath . '/' . $name . '.php';
        file_put_contents($layoutFile, $content);
    }

    /**
     * Eliminar layout
     */
    public function deleteLayout(string $name): bool
    {
        $layoutFile = $this->layoutPath . '/' . $name . '.php';
        
        if (file_exists($layoutFile)) {
            return unlink($layoutFile);
        }
        
        return false;
    }

    /**
     * Obtener contenido de layout
     */
    public function getLayoutContent(string $name): string
    {
        $layoutFile = $this->layoutPath . '/' . $name . '.php';
        
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$name}");
        }
        
        return file_get_contents($layoutFile);
    }

    /**
     * Actualizar contenido de layout
     */
    public function updateLayout(string $name, string $content): void
    {
        $layoutFile = $this->layoutPath . '/' . $name . '.php';
        file_put_contents($layoutFile, $content);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'layout_path' => $this->layoutPath,
            'current_layout' => $this->currentLayout,
            'available_layouts' => $this->getAvailableLayouts(),
            'blocks_count' => count($this->blocks),
            'stack_count' => count($this->stack)
        ];
    }
}
