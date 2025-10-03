<?php

namespace Tero\I18n;

use Tero\Config\ConfigManager;
use Tero\I18n\Contracts\TranslationManagerInterface;
use Tero\I18n\Contracts\LocaleInterface;
use Tero\I18n\Exceptions\TranslationException;

/**
 * TranslationManager - Gestor de traducciones e internacionalización
 * 
 * @package Tero\I18n
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class TranslationManager implements TranslationManagerInterface
{
    private ConfigManager $config;
    private array $locales = [];
    private array $translations = [];
    private string $defaultLocale;
    private string $fallbackLocale;
    private ?string $currentLocale = null;
    private array $loadedFiles = [];
    private bool $autoDiscovery = true;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->autoDiscovery = $config->get('I18N_AUTO_DISCOVERY', true, 'bool');
        $this->defaultLocale = $config->get('I18N_DEFAULT_LOCALE', 'en');
        $this->fallbackLocale = $config->get('I18N_FALLBACK_LOCALE', 'en');
        
        $this->initializeLocales();
        $this->loadTranslations();
        
        if ($this->autoDiscovery) {
            $this->discoverTranslations();
        }
    }

    /**
     * Inicializar locales
     */
    private function initializeLocales(): void
    {
        // Locales por defecto
        $this->locales['en'] = new Locale('en', 'English', 'en_US.UTF-8');
        $this->locales['es'] = new Locale('es', 'Español', 'es_ES.UTF-8');
        $this->locales['fr'] = new Locale('fr', 'Français', 'fr_FR.UTF-8');
        $this->locales['de'] = new Locale('de', 'Deutsch', 'de_DE.UTF-8');
        $this->locales['it'] = new Locale('it', 'Italiano', 'it_IT.UTF-8');
        $this->locales['pt'] = new Locale('pt', 'Português', 'pt_PT.UTF-8');
        $this->locales['ru'] = new Locale('ru', 'Русский', 'ru_RU.UTF-8');
        $this->locales['zh'] = new Locale('zh', '中文', 'zh_CN.UTF-8');
        $this->locales['ja'] = new Locale('ja', '日本語', 'ja_JP.UTF-8');
        $this->locales['ko'] = new Locale('ko', '한국어', 'ko_KR.UTF-8');
    }

    /**
     * Cargar traducciones
     */
    private function loadTranslations(): void
    {
        $translationsPath = $this->config->get('I18N_TRANSLATIONS_PATH', 'resources/lang');
        
        if (!is_dir($translationsPath)) {
            return;
        }
        
        foreach ($this->locales as $locale) {
            $this->loadLocaleTranslations($locale->getCode(), $translationsPath);
        }
    }

    /**
     * Cargar traducciones de un locale
     */
    private function loadLocaleTranslations(string $locale, string $basePath): void
    {
        $localePath = $basePath . '/' . $locale;
        
        if (!is_dir($localePath)) {
            return;
        }
        
        $files = glob($localePath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $translations = include $file;
            
            if (is_array($translations)) {
                $this->translations[$locale][$key] = $translations;
                $this->loadedFiles[$locale][] = $file;
            }
        }
    }

    /**
     * Descubrir traducciones automáticamente
     */
    private function discoverTranslations(): void
    {
        $translationsPath = $this->config->get('I18N_TRANSLATIONS_PATH', 'resources/lang');
        
        if (!is_dir($translationsPath)) {
            return;
        }
        
        $directories = glob($translationsPath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $directory) {
            $locale = basename($directory);
            
            if (!isset($this->locales[$locale])) {
                $this->locales[$locale] = new Locale($locale, ucfirst($locale), $locale . '.UTF-8');
            }
            
            $this->loadLocaleTranslations($locale, $translationsPath);
        }
    }

    /**
     * Registrar locale personalizado
     */
    public function registerLocale(LocaleInterface $locale): void
    {
        $this->locales[$locale->getCode()] = $locale;
    }

    /**
     * Establecer locale actual
     */
    public function setLocale(string $locale): void
    {
        if (!isset($this->locales[$locale])) {
            throw new TranslationException("Locale '{$locale}' not found");
        }
        
        $this->currentLocale = $locale;
        
        // Establecer locale del sistema
        $localeObject = $this->locales[$locale];
        setlocale(LC_ALL, $localeObject->getSystemLocale());
        
        // Establecer timezone si está configurado
        $timezone = $this->config->get("I18N_LOCALE_{$locale}_TIMEZONE");
        if ($timezone) {
            date_default_timezone_set($timezone);
        }
    }

    /**
     * Obtener locale actual
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale ?? $this->defaultLocale;
    }

    /**
     * Obtener locale por defecto
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Obtener locale de fallback
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Traducir clave
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $translation = $this->getTranslation($key, $locale);
        
        if ($translation === null && $locale !== $this->fallbackLocale) {
            $translation = $this->getTranslation($key, $this->fallbackLocale);
        }
        
        if ($translation === null) {
            return $key; // Devolver la clave si no se encuentra traducción
        }
        
        return $this->replacePlaceholders($translation, $replace);
    }

    /**
     * Traducir clave con pluralización
     */
    public function transChoice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $translation = $this->getTranslation($key, $locale);
        
        if ($translation === null && $locale !== $this->fallbackLocale) {
            $translation = $this->getTranslation($key, $this->fallbackLocale);
        }
        
        if ($translation === null) {
            return $key;
        }
        
        // Aplicar pluralización
        $translation = $this->applyPluralization($translation, $number, $locale);
        
        // Reemplazar placeholders
        $replace['count'] = $number;
        return $this->replacePlaceholders($translation, $replace);
    }

    /**
     * Obtener traducción
     */
    private function getTranslation(string $key, string $locale): ?string
    {
        $keys = explode('.', $key);
        $translation = $this->translations[$locale] ?? [];
        
        foreach ($keys as $keyPart) {
            if (!isset($translation[$keyPart])) {
                return null;
            }
            $translation = $translation[$keyPart];
        }
        
        return is_string($translation) ? $translation : null;
    }

    /**
     * Aplicar pluralización
     */
    private function applyPluralization(string $translation, int $number, string $locale): string
    {
        // Si la traducción contiene múltiples formas separadas por |
        if (str_contains($translation, '|')) {
            $forms = explode('|', $translation);
            $formIndex = $this->getPluralForm($number, $locale);
            
            if (isset($forms[$formIndex])) {
                return trim($forms[$formIndex]);
            }
        }
        
        return $translation;
    }

    /**
     * Obtener forma plural
     */
    private function getPluralForm(int $number, string $locale): int
    {
        // Reglas de pluralización básicas
        $rules = [
            'en' => fn($n) => $n === 1 ? 0 : 1,
            'es' => fn($n) => $n === 1 ? 0 : 1,
            'fr' => fn($n) => $n <= 1 ? 0 : 1,
            'de' => fn($n) => $n === 1 ? 0 : 1,
            'it' => fn($n) => $n === 1 ? 0 : 1,
            'pt' => fn($n) => $n === 1 ? 0 : 1,
            'ru' => fn($n) => $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),
            'zh' => fn($n) => 0,
            'ja' => fn($n) => 0,
            'ko' => fn($n) => 0,
        ];
        
        $rule = $rules[$locale] ?? $rules['en'];
        return $rule($number);
    }

    /**
     * Reemplazar placeholders
     */
    private function replacePlaceholders(string $translation, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $translation = str_replace(':' . $key, $value, $translation);
            $translation = str_replace('{' . $key . '}', $value, $translation);
        }
        
        return $translation;
    }

    /**
     * Formatear fecha
     */
    public function formatDate(\DateTime $date, string $format = null, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        $format = $format ?? $this->config->get("I18N_LOCALE_{$locale}_DATE_FORMAT", 'Y-m-d');
        
        return $date->format($format);
    }

    /**
     * Formatear fecha y hora
     */
    public function formatDateTime(\DateTime $date, string $format = null, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        $format = $format ?? $this->config->get("I18N_LOCALE_{$locale}_DATETIME_FORMAT", 'Y-m-d H:i:s');
        
        return $date->format($format);
    }

    /**
     * Formatear número
     */
    public function formatNumber(float $number, int $decimals = 2, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $localeObject = $this->locales[$locale];
        $systemLocale = $localeObject->getSystemLocale();
        
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Formatear moneda
     */
    public function formatCurrency(float $amount, string $currency = 'USD', ?string $locale = null): string
    {
        $locale = $locale ?? $this->getCurrentLocale();
        
        $currencySymbol = $this->config->get("I18N_CURRENCY_{$currency}_SYMBOL", $currency);
        $currencyPosition = $this->config->get("I18N_CURRENCY_{$currency}_POSITION", 'before');
        
        $formattedAmount = $this->formatNumber($amount, 2, $locale);
        
        return $currencyPosition === 'before' ? 
            $currencySymbol . ' ' . $formattedAmount : 
            $formattedAmount . ' ' . $currencySymbol;
    }

    /**
     * Obtener todos los locales
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * Obtener locale específico
     */
    public function getLocale(string $code): ?LocaleInterface
    {
        return $this->locales[$code] ?? null;
    }

    /**
     * Verificar si locale existe
     */
    public function hasLocale(string $code): bool
    {
        return isset($this->locales[$code]);
    }

    /**
     * Obtener traducciones cargadas
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Obtener archivos cargados
     */
    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'auto_discovery' => $this->autoDiscovery,
            'default_locale' => $this->defaultLocale,
            'fallback_locale' => $this->fallbackLocale,
            'current_locale' => $this->getCurrentLocale(),
            'locales_count' => count($this->locales),
            'available_locales' => array_keys($this->locales),
            'translations_count' => count($this->translations),
            'loaded_files_count' => count($this->loadedFiles, COUNT_RECURSIVE)
        ];
    }
}
