<?php

namespace Tero\Views;

use Tero\Config\ConfigManager;
use Tero\Templates\TemplateEngine;
use Tero\Views\Contracts\ViewManagerInterface;

/**
 * ViewManager - Gestor de vistas moderno
 * 
 * @package Tero\Views
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class ViewManager implements ViewManagerInterface
{
    private ConfigManager $config;
    private TemplateEngine $templateEngine;
    private string $viewPath;
    private array $sharedData = [];
    private array $viewComposers = [];
    private array $viewCreators = [];

    public function __construct(ConfigManager $config, TemplateEngine $templateEngine)
    {
        $this->config = $config;
        $this->templateEngine = $templateEngine;
        $this->viewPath = $config->get('VIEW_PATH', 'resources/views');
        
        $this->registerDefaultComposers();
    }

    /**
     * Registrar composers por defecto
     */
    private function registerDefaultComposers(): void
    {
        // Composer para todas las vistas
        $this->composer('*', function($view) {
            $view->with([
                'app_name' => $this->config->get('APP_NAME', 'Tero Framework'),
                'app_url' => $this->config->get('APP_URL', 'http://localhost'),
                'app_version' => $this->config->get('APP_VERSION', '4.2.2-dev'),
                'current_time' => date('Y-m-d H:i:s'),
                'csrf_token' => $this->templateEngine->csrfToken()
            ]);
        });
    }

    /**
     * Renderizar vista
     */
    public function render(string $view, array $data = []): string
    {
        $viewData = array_merge($this->sharedData, $data);
        
        // Aplicar composers
        $viewData = $this->applyComposers($view, $viewData);
        
        // Aplicar creators
        $viewData = $this->applyCreators($view, $viewData);
        
        return $this->templateEngine->render($view, $viewData);
    }

    /**
     * Crear vista
     */
    public function make(string $view, array $data = []): \Tero\Views\View
    {
        return new \Tero\Views\View($this, $view, $data);
    }

    /**
     * Compartir datos con todas las vistas
     */
    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /**
     * Compartir múltiples datos
     */
    public function shareArray(array $data): void
    {
        $this->sharedData = array_merge($this->sharedData, $data);
    }

    /**
     * Registrar composer
     */
    public function composer(string $view, callable $callback): void
    {
        $this->viewComposers[$view][] = $callback;
    }

    /**
     * Registrar creator
     */
    public function creator(string $view, callable $callback): void
    {
        $this->viewCreators[$view][] = $callback;
    }

    /**
     * Aplicar composers
     */
    private function applyComposers(string $view, array $data): array
    {
        $composers = array_merge(
            $this->viewComposers['*'] ?? [],
            $this->viewComposers[$view] ?? []
        );
        
        foreach ($composers as $composer) {
            $viewInstance = new \Tero\Views\View($this, $view, $data);
            $composer($viewInstance);
            $data = array_merge($data, $viewInstance->getData());
        }
        
        return $data;
    }

    /**
     * Aplicar creators
     */
    private function applyCreators(string $view, array $data): array
    {
        $creators = $this->viewCreators[$view] ?? [];
        
        foreach ($creators as $creator) {
            $viewInstance = new \Tero\Views\View($this, $view, $data);
            $creator($viewInstance);
            $data = array_merge($data, $viewInstance->getData());
        }
        
        return $data;
    }

    /**
     * Verificar si vista existe
     */
    public function exists(string $view): bool
    {
        $viewFile = $this->viewPath . '/' . ltrim($view, '/') . '.php';
        return file_exists($viewFile);
    }

    /**
     * Obtener ruta de vista
     */
    public function getViewPath(string $view): string
    {
        return $this->viewPath . '/' . ltrim($view, '/') . '.php';
    }

    /**
     * Obtener todas las vistas disponibles
     */
    public function getAvailableViews(): array
    {
        $views = [];
        $files = glob($this->viewPath . '/**/*.php', GLOB_BRACE);
        
        foreach ($files as $file) {
            $relativePath = str_replace($this->viewPath . '/', '', $file);
            $viewName = str_replace('.php', '', $relativePath);
            $views[] = $viewName;
        }
        
        return $views;
    }

    /**
     * Limpiar cache de vistas
     */
    public function clearCache(): int
    {
        return $this->templateEngine->clearCache();
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'view_path' => $this->viewPath,
            'shared_data_count' => count($this->sharedData),
            'composers_count' => count($this->viewComposers),
            'creators_count' => count($this->viewCreators),
            'available_views' => count($this->getAvailableViews()),
            'template_engine' => $this->templateEngine->getDebugInfo()
        ];
    }
}
