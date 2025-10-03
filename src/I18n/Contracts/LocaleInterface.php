<?php

namespace Tero\I18n\Contracts;

/**
 * LocaleInterface - Interfaz para locales de internacionalización
 * 
 * @package Tero\I18n\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface LocaleInterface
{
    /**
     * Obtener código del locale
     */
    public function getCode(): string;

    /**
     * Obtener nombre del locale
     */
    public function getName(): string;

    /**
     * Obtener locale del sistema
     */
    public function getSystemLocale(): string;

    /**
     * Obtener dirección del texto
     */
    public function getDirection(): string;

    /**
     * Obtener formato de fecha
     */
    public function getDateFormat(): string;

    /**
     * Obtener formato de hora
     */
    public function getTimeFormat(): string;

    /**
     * Obtener formato de fecha y hora
     */
    public function getDateTimeFormat(): string;

    /**
     * Obtener formato de número
     */
    public function getNumberFormat(): string;

    /**
     * Obtener formato de moneda
     */
    public function getCurrencyFormat(): string;

    /**
     * Obtener reglas de pluralización
     */
    public function getPluralRules(): array;

    /**
     * Obtener metadata
     */
    public function getMetadata(): array;

    /**
     * Verificar si es RTL
     */
    public function isRtl(): bool;

    /**
     * Verificar si es LTR
     */
    public function isLtr(): bool;

    /**
     * Obtener forma plural para un número
     */
    public function getPluralForm(int $number): int;

    /**
     * Formatear fecha
     */
    public function formatDate(\DateTime $date): string;

    /**
     * Formatear hora
     */
    public function formatTime(\DateTime $time): string;

    /**
     * Formatear fecha y hora
     */
    public function formatDateTime(\DateTime $datetime): string;

    /**
     * Formatear número
     */
    public function formatNumber(float $number, int $decimals = 2): string;

    /**
     * Formatear moneda
     */
    public function formatCurrency(float $amount, string $currency = 'USD'): string;
}
