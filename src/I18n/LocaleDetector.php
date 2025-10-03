<?php

namespace Tero\I18n;

use Tero\Config\ConfigManager;
use Tero\I18n\Contracts\LocaleDetectorInterface;

/**
 * LocaleDetector - Detector de locale automático
 * 
 * @package Tero\I18n
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class LocaleDetector implements LocaleDetectorInterface
{
    private ConfigManager $config;
    private array $availableLocales;
    private string $defaultLocale;
    private array $detectionMethods;

    public function __construct(ConfigManager $config, array $availableLocales, string $defaultLocale = 'en')
    {
        $this->config = $config;
        $this->availableLocales = $availableLocales;
        $this->defaultLocale = $defaultLocale;
        $this->detectionMethods = $config->get('I18N_DETECTION_METHODS', [
            'session',
            'cookie',
            'header',
            'subdomain',
            'domain',
            'parameter'
        ], 'array');
    }

    /**
     * Detectar locale
     */
    public function detect(): string
    {
        foreach ($this->detectionMethods as $method) {
            $locale = $this->detectByMethod($method);
            
            if ($locale && $this->isValidLocale($locale)) {
                return $locale;
            }
        }
        
        return $this->defaultLocale;
    }

    /**
     * Detectar por método específico
     */
    private function detectByMethod(string $method): ?string
    {
        return match ($method) {
            'session' => $this->detectFromSession(),
            'cookie' => $this->detectFromCookie(),
            'header' => $this->detectFromHeader(),
            'subdomain' => $this->detectFromSubdomain(),
            'domain' => $this->detectFromDomain(),
            'parameter' => $this->detectFromParameter(),
            default => null
        };
    }

    /**
     * Detectar desde sesión
     */
    private function detectFromSession(): ?string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return $_SESSION['locale'] ?? null;
        }
        
        return null;
    }

    /**
     * Detectar desde cookie
     */
    private function detectFromCookie(): ?string
    {
        return $_COOKIE['locale'] ?? null;
    }

    /**
     * Detectar desde header Accept-Language
     */
    private function detectFromHeader(): ?string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        if (empty($acceptLanguage)) {
            return null;
        }
        
        $languages = $this->parseAcceptLanguage($acceptLanguage);
        
        foreach ($languages as $language) {
            $locale = $this->normalizeLanguage($language);
            
            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }
        
        return null;
    }

    /**
     * Detectar desde subdominio
     */
    private function detectFromSubdomain(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($host)) {
            return null;
        }
        
        $parts = explode('.', $host);
        
        if (count($parts) > 2) {
            $subdomain = $parts[0];
            
            if ($this->isValidLocale($subdomain)) {
                return $subdomain;
            }
        }
        
        return null;
    }

    /**
     * Detectar desde dominio
     */
    private function detectFromDomain(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($host)) {
            return null;
        }
        
        $domainLocaleMap = $this->config->get('I18N_DOMAIN_LOCALE_MAP', [], 'array');
        
        if (isset($domainLocaleMap[$host])) {
            return $domainLocaleMap[$host];
        }
        
        return null;
    }

    /**
     * Detectar desde parámetro
     */
    private function detectFromParameter(): ?string
    {
        $parameter = $this->config->get('I18N_LOCALE_PARAMETER', 'locale');
        
        return $_GET[$parameter] ?? $_POST[$parameter] ?? null;
    }

    /**
     * Parsear header Accept-Language
     */
    private function parseAcceptLanguage(string $acceptLanguage): array
    {
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (str_contains($part, ';')) {
                $part = explode(';', $part)[0];
            }
            
            $languages[] = trim($part);
        }
        
        return $languages;
    }

    /**
     * Normalizar idioma
     */
    private function normalizeLanguage(string $language): string
    {
        // Convertir en-US a en, es-ES a es, etc.
        if (str_contains($language, '-')) {
            return explode('-', $language)[0];
        }
        
        return $language;
    }

    /**
     * Verificar si locale es válido
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, $this->availableLocales, true);
    }

    /**
     * Establecer locale en sesión
     */
    public function setSessionLocale(string $locale): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['locale'] = $locale;
    }

    /**
     * Establecer locale en cookie
     */
    public function setCookieLocale(string $locale, int $expire = 0): void
    {
        if ($expire === 0) {
            $expire = time() + (365 * 24 * 60 * 60); // 1 año
        }
        
        setcookie('locale', $locale, $expire, '/');
    }

    /**
     * Obtener locale desde sesión
     */
    public function getSessionLocale(): ?string
    {
        return $this->detectFromSession();
    }

    /**
     * Obtener locale desde cookie
     */
    public function getCookieLocale(): ?string
    {
        return $this->detectFromCookie();
    }

    /**
     * Obtener locale desde header
     */
    public function getHeaderLocale(): ?string
    {
        return $this->detectFromHeader();
    }

    /**
     * Obtener locale desde subdominio
     */
    public function getSubdomainLocale(): ?string
    {
        return $this->detectFromSubdomain();
    }

    /**
     * Obtener locale desde dominio
     */
    public function getDomainLocale(): ?string
    {
        return $this->detectFromDomain();
    }

    /**
     * Obtener locale desde parámetro
     */
    public function getParameterLocale(): ?string
    {
        return $this->detectFromParameter();
    }

    /**
     * Obtener métodos de detección
     */
    public function getDetectionMethods(): array
    {
        return $this->detectionMethods;
    }

    /**
     * Establecer métodos de detección
     */
    public function setDetectionMethods(array $methods): void
    {
        $this->detectionMethods = $methods;
    }

    /**
     * Obtener locales disponibles
     */
    public function getAvailableLocales(): array
    {
        return $this->availableLocales;
    }

    /**
     * Establecer locales disponibles
     */
    public function setAvailableLocales(array $locales): void
    {
        $this->availableLocales = $locales;
    }

    /**
     * Obtener locale por defecto
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Establecer locale por defecto
     */
    public function setDefaultLocale(string $locale): void
    {
        $this->defaultLocale = $locale;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'available_locales' => $this->availableLocales,
            'default_locale' => $this->defaultLocale,
            'detection_methods' => $this->detectionMethods,
            'session_locale' => $this->getSessionLocale(),
            'cookie_locale' => $this->getCookieLocale(),
            'header_locale' => $this->getHeaderLocale(),
            'subdomain_locale' => $this->getSubdomainLocale(),
            'domain_locale' => $this->getDomainLocale(),
            'parameter_locale' => $this->getParameterLocale(),
            'detected_locale' => $this->detect()
        ];
    }
}
