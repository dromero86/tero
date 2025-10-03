<?php

namespace Tero\I18n\Contracts;

/**
 * LocaleDetectorInterface - Interfaz para detectores de locale
 * 
 * @package Tero\I18n\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface LocaleDetectorInterface
{
    /**
     * Detectar locale
     */
    public function detect(): string;

    /**
     * Establecer locale en sesión
     */
    public function setSessionLocale(string $locale): void;

    /**
     * Establecer locale en cookie
     */
    public function setCookieLocale(string $locale, int $expire = 0): void;

    /**
     * Obtener locale desde sesión
     */
    public function getSessionLocale(): ?string;

    /**
     * Obtener locale desde cookie
     */
    public function getCookieLocale(): ?string;

    /**
     * Obtener locale desde header
     */
    public function getHeaderLocale(): ?string;

    /**
     * Obtener locale desde subdominio
     */
    public function getSubdomainLocale(): ?string;

    /**
     * Obtener locale desde dominio
     */
    public function getDomainLocale(): ?string;

    /**
     * Obtener locale desde parámetro
     */
    public function getParameterLocale(): ?string;

    /**
     * Obtener métodos de detección
     */
    public function getDetectionMethods(): array;

    /**
     * Establecer métodos de detección
     */
    public function setDetectionMethods(array $methods): void;

    /**
     * Obtener locales disponibles
     */
    public function getAvailableLocales(): array;

    /**
     * Establecer locales disponibles
     */
    public function setAvailableLocales(array $locales): void;

    /**
     * Obtener locale por defecto
     */
    public function getDefaultLocale(): string;

    /**
     * Establecer locale por defecto
     */
    public function setDefaultLocale(string $locale): void;
}
