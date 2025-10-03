<?php

namespace Tero\Templates;

use Tero\Config\ConfigManager;
use Tero\Templates\Contracts\TemplateEngineInterface;
use Tero\Templates\Exceptions\TemplateException;

/**
 * TemplateEngine - Motor de templates moderno
 * 
 * @package Tero\Templates
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class TemplateEngine implements TemplateEngineInterface
{
    private ConfigManager $config;
    private string $templatePath;
    private string $cachePath;
    private array $variables = [];
    private array $helpers = [];
    private array $blocks = [];
    private array $layouts = [];
    private bool $autoEscape;
    private bool $debugMode;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->templatePath = $config->get('TEMPLATE_PATH', 'resources/views');
        $this->cachePath = $config->get('TEMPLATE_CACHE_PATH', 'storage/cache/templates');
        $this->autoEscape = $config->get('TEMPLATE_AUTO_ESCAPE', true, 'bool');
        $this->debugMode = $config->get('APP_DEBUG', false, 'bool');
        
        $this->ensureDirectories();
        $this->registerDefaultHelpers();
    }

    /**
     * Asegurar que los directorios existen
     */
    private function ensureDirectories(): void
    {
        if (!is_dir($this->templatePath)) {
            mkdir($this->templatePath, 0755, true);
        }
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Registrar helpers por defecto
     */
    private function registerDefaultHelpers(): void
    {
        $this->registerHelper('escape', [$this, 'escape']);
        $this->registerHelper('date', [$this, 'formatDate']);
        $this->registerHelper('number', [$this, 'formatNumber']);
        $this->registerHelper('url', [$this, 'generateUrl']);
        $this->registerHelper('asset', [$this, 'assetUrl']);
        $this->registerHelper('csrf', [$this, 'csrfToken']);
        $this->registerHelper('old', [$this, 'oldInput']);
        $this->registerHelper('config', [$this, 'getConfig']);
    }

    /**
     * Renderizar template
     */
    public function render(string $template, array $variables = []): string
    {
        $this->variables = array_merge($this->variables, $variables);
        
        $templateFile = $this->getTemplateFile($template);
        $compiledFile = $this->compileTemplate($templateFile);
        
        return $this->executeTemplate($compiledFile);
    }

    /**
     * Obtener archivo de template
     */
    private function getTemplateFile(string $template): string
    {
        $templateFile = $this->templatePath . '/' . ltrim($template, '/') . '.php';
        
        if (!file_exists($templateFile)) {
            throw new TemplateException("Template not found: {$template}");
        }
        
        return $templateFile;
    }

    /**
     * Compilar template
     */
    private function compileTemplate(string $templateFile): string
    {
        $content = file_get_contents($templateFile);
        $compiledContent = $this->parseTemplate($content);
        
        $cacheFile = $this->cachePath . '/' . md5($templateFile) . '.php';
        
        if (!$this->debugMode || !file_exists($cacheFile) || filemtime($templateFile) > filemtime($cacheFile)) {
            file_put_contents($cacheFile, $compiledContent);
        }
        
        return $cacheFile;
    }

    /**
     * Parsear template
     */
    private function parseTemplate(string $content): string
    {
        // Parsear extends
        $content = $this->parseExtends($content);
        
        // Parsear blocks
        $content = $this->parseBlocks($content);
        
        // Parsear variables
        $content = $this->parseVariables($content);
        
        // Parsear helpers
        $content = $this->parseHelpers($content);
        
        // Parsear includes
        $content = $this->parseIncludes($content);
        
        // Parsear loops
        $content = $this->parseLoops($content);
        
        // Parsear conditionals
        $content = $this->parseConditionals($content);
        
        return $content;
    }

    /**
     * Parsear extends
     */
    private function parseExtends(string $content): string
    {
        return preg_replace_callback('/@extends\([\'"]([^\'"]+)[\'"]\)/', function($matches) {
            return "<?php \$this->extends('{$matches[1]}'); ?>";
        }, $content);
    }

    /**
     * Parsear blocks
     */
    private function parseBlocks(string $content): string
    {
        // Parsear @section
        $content = preg_replace_callback('/@section\([\'"]([^\'"]+)[\'"]\)(.*?)@endsection/s', function($matches) {
            $name = $matches[1];
            $content = $matches[2];
            return "<?php \$this->startSection('{$name}'); ?>{$content}<?php \$this->endSection(); ?>";
        }, $content);
        
        // Parsear @yield
        $content = preg_replace_callback('/@yield\([\'"]([^\'"]+)[\'"]\)/', function($matches) {
            return "<?php echo \$this->yieldSection('{$matches[1]}'); ?>";
        }, $content);
        
        return $content;
    }

    /**
     * Parsear variables
     */
    private function parseVariables(string $content): string
    {
        // Parsear {{ variable }}
        $content = preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function($matches) {
            $variable = trim($matches[1]);
            return "<?php echo \$this->getVariable('{$variable}'); ?>";
        }, $content);
        
        // Parsear {!! variable !!}
        $content = preg_replace_callback('/\{!!\s*([^}]+)\s*!!\}/', function($matches) {
            $variable = trim($matches[1]);
            return "<?php echo \$this->getVariable('{$variable}', false); ?>";
        }, $content);
        
        return $content;
    }

    /**
     * Parsear helpers
     */
    private function parseHelpers(string $content): string
    {
        return preg_replace_callback('/@(\w+)\(([^)]*)\)/', function($matches) {
            $helper = $matches[1];
            $params = $matches[2];
            return "<?php echo \$this->callHelper('{$helper}', [{$params}]); ?>";
        }, $content);
    }

    /**
     * Parsear includes
     */
    private function parseIncludes(string $content): string
    {
        return preg_replace_callback('/@include\([\'"]([^\'"]+)[\'"]\)/', function($matches) {
            return "<?php echo \$this->include('{$matches[1]}'); ?>";
        }, $content);
    }

    /**
     * Parsear loops
     */
    private function parseLoops(string $content): string
    {
        // Parsear @foreach
        $content = preg_replace_callback('/@foreach\(([^)]+)\)(.*?)@endforeach/s', function($matches) {
            $condition = $matches[1];
            $content = $matches[2];
            return "<?php foreach({$condition}): ?>{$content}<?php endforeach; ?>";
        }, $content);
        
        // Parsear @for
        $content = preg_replace_callback('/@for\(([^)]+)\)(.*?)@endfor/s', function($matches) {
            $condition = $matches[1];
            $content = $matches[2];
            return "<?php for({$condition}): ?>{$content}<?php endfor; ?>";
        }, $content);
        
        return $content;
    }

    /**
     * Parsear conditionals
     */
    private function parseConditionals(string $content): string
    {
        // Parsear @if
        $content = preg_replace_callback('/@if\(([^)]+)\)(.*?)@endif/s', function($matches) {
            $condition = $matches[1];
            $content = $matches[2];
            return "<?php if({$condition}): ?>{$content}<?php endif; ?>";
        }, $content);
        
        // Parsear @elseif
        $content = preg_replace_callback('/@elseif\(([^)]+)\)/', function($matches) {
            return "<?php elseif({$matches[1]}): ?>";
        }, $content);
        
        // Parsear @else
        $content = preg_replace_callback('/@else/', function($matches) {
            return "<?php else: ?>";
        }, $content);
        
        return $content;
    }

    /**
     * Ejecutar template compilado
     */
    private function executeTemplate(string $compiledFile): string
    {
        ob_start();
        
        try {
            include $compiledFile;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new TemplateException("Template execution failed: " . $e->getMessage());
        }
    }

    /**
     * Obtener variable
     */
    public function getVariable(string $name, bool $escape = true): string
    {
        $value = $this->getNestedVariable($name);
        
        if ($escape && $this->autoEscape) {
            return $this->escape($value);
        }
        
        return (string) $value;
    }

    /**
     * Obtener variable anidada
     */
    private function getNestedVariable(string $name): mixed
    {
        $keys = explode('.', $name);
        $value = $this->variables;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    /**
     * Llamar helper
     */
    public function callHelper(string $name, array $params = []): string
    {
        if (!isset($this->helpers[$name])) {
            throw new TemplateException("Helper '{$name}' not found");
        }
        
        $helper = $this->helpers[$name];
        
        if (is_callable($helper)) {
            return (string) call_user_func_array($helper, $params);
        }
        
        throw new TemplateException("Helper '{$name}' is not callable");
    }

    /**
     * Registrar helper
     */
    public function registerHelper(string $name, callable $helper): void
    {
        $this->helpers[$name] = $helper;
    }

    /**
     * Incluir template
     */
    public function include(string $template): string
    {
        return $this->render($template, $this->variables);
    }

    /**
     * Extender layout
     */
    public function extends(string $layout): void
    {
        $this->layouts[] = $layout;
    }

    /**
     * Iniciar sección
     */
    public function startSection(string $name): void
    {
        ob_start();
        $this->currentSection = $name;
    }

    /**
     * Finalizar sección
     */
    public function endSection(): void
    {
        if (isset($this->currentSection)) {
            $this->blocks[$this->currentSection] = ob_get_clean();
            unset($this->currentSection);
        }
    }

    /**
     * Obtener sección
     */
    public function yieldSection(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    /**
     * Helper: Escapar HTML
     */
    public function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Helper: Formatear fecha
     */
    public function formatDate(string $date, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, strtotime($date));
    }

    /**
     * Helper: Formatear número
     */
    public function formatNumber(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals);
    }

    /**
     * Helper: Generar URL
     */
    public function generateUrl(string $path, array $params = []): string
    {
        $url = $this->config->get('APP_URL', 'http://localhost') . '/' . ltrim($path, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }

    /**
     * Helper: URL de asset
     */
    public function assetUrl(string $path): string
    {
        return $this->config->get('APP_URL', 'http://localhost') . '/assets/' . ltrim($path, '/');
    }

    /**
     * Helper: Token CSRF
     */
    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Helper: Input anterior
     */
    public function oldInput(string $name, mixed $default = ''): string
    {
        return $_SESSION['old_input'][$name] ?? $default;
    }

    /**
     * Helper: Obtener configuración
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /**
     * Limpiar cache
     */
    public function clearCache(): int
    {
        $files = glob($this->cachePath . '/*.php');
        $deleted = 0;
        
        foreach ($files as $file) {
            unlink($file);
            $deleted++;
        }
        
        return $deleted;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'template_path' => $this->templatePath,
            'cache_path' => $this->cachePath,
            'auto_escape' => $this->autoEscape,
            'debug_mode' => $this->debugMode,
            'variables_count' => count($this->variables),
            'helpers_count' => count($this->helpers),
            'blocks_count' => count($this->blocks),
            'layouts_count' => count($this->layouts)
        ];
    }
}
