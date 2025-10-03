<?php

namespace Tero\I18n\Contracts;

/**
 * TranslationManagerInterface - Interfaz para el gestor de traducciones
 * 
 * @package Tero\I18n\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface TranslationManagerInterface
{
    /**
     * Registrar locale personalizado
     */
    public function registerLocale(LocaleInterface $locale): void;

    /**
     * Establecer locale actual
     */
    public function setLocale(string $locale): void;

    /**
     * Obtener locale actual
     */
    public function getCurrentLocale(): string;

    /**
     * Obtener locale por defecto
     */
    public function getDefaultLocale(): string;

    /**
     * Obtener locale de fallback
     */
    public function getFallbackLocale(): string;

    /**
     * Traducir clave
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string;

    /**
     * Traducir clave con pluralización
     */
    public function transChoice(string $key, int $number, array $replace = [], ?string $locale = null): string;

    /**
     * Formatear fecha
     */
    public function formatDate(\DateTime $date, string $format = null, ?string $locale = null): string;

    /**
     * Formatear fecha y hora
     */
    public function formatDateTime(\DateTime $date, string $format = null, ?string $locale = null): string;

    /**
     * Formatear número
     */
    public function formatNumber(float $number, int $decimals = 2, ?string $locale = null): string;

    /**
     * Formatear moneda
     */
    public function formatCurrency(float $amount, string $currency = 'USD', ?string $locale = null): string;

    /**
     * Obtener todos los locales
     */
    public function getLocales(): array;

    /**
     * Obtener locale específico
     */
    public function getLocale(string $code): ?LocaleInterface;

    /**
     * Verificar si locale existe
     */
    public function hasLocale(string $code): bool;
}
