<?php

namespace Tero\I18n;

use Tero\I18n\Contracts\LocaleInterface;

/**
 * Locale - Representa un locale de internacionalización
 * 
 * @package Tero\I18n
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class Locale implements LocaleInterface
{
    private string $code;
    private string $name;
    private string $systemLocale;
    private string $direction;
    private string $dateFormat;
    private string $timeFormat;
    private string $datetimeFormat;
    private string $numberFormat;
    private string $currencyFormat;
    private array $pluralRules;
    private array $metadata;

    public function __construct(
        string $code,
        string $name,
        string $systemLocale,
        array $options = []
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->systemLocale = $systemLocale;
        $this->direction = $options['direction'] ?? 'ltr';
        $this->dateFormat = $options['date_format'] ?? 'Y-m-d';
        $this->timeFormat = $options['time_format'] ?? 'H:i:s';
        $this->datetimeFormat = $options['datetime_format'] ?? 'Y-m-d H:i:s';
        $this->numberFormat = $options['number_format'] ?? '0,0.00';
        $this->currencyFormat = $options['currency_format'] ?? '$0,0.00';
        $this->pluralRules = $options['plural_rules'] ?? [];
        $this->metadata = $options['metadata'] ?? [];
    }

    /**
     * Obtener código del locale
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Obtener nombre del locale
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtener locale del sistema
     */
    public function getSystemLocale(): string
    {
        return $this->systemLocale;
    }

    /**
     * Obtener dirección del texto
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * Obtener formato de fecha
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * Obtener formato de hora
     */
    public function getTimeFormat(): string
    {
        return $this->timeFormat;
    }

    /**
     * Obtener formato de fecha y hora
     */
    public function getDateTimeFormat(): string
    {
        return $this->datetimeFormat;
    }

    /**
     * Obtener formato de número
     */
    public function getNumberFormat(): string
    {
        return $this->numberFormat;
    }

    /**
     * Obtener formato de moneda
     */
    public function getCurrencyFormat(): string
    {
        return $this->currencyFormat;
    }

    /**
     * Obtener reglas de pluralización
     */
    public function getPluralRules(): array
    {
        return $this->pluralRules;
    }

    /**
     * Obtener metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Obtener valor específico de metadata
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Establecer valor específico de metadata
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Verificar si es RTL
     */
    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /**
     * Verificar si es LTR
     */
    public function isLtr(): bool
    {
        return $this->direction === 'ltr';
    }

    /**
     * Obtener forma plural para un número
     */
    public function getPluralForm(int $number): int
    {
        if (empty($this->pluralRules)) {
            return $number === 1 ? 0 : 1;
        }
        
        foreach ($this->pluralRules as $rule) {
            if (isset($rule['condition']) && isset($rule['form'])) {
                if (eval("return {$rule['condition']};")) {
                    return $rule['form'];
                }
            }
        }
        
        return 0;
    }

    /**
     * Formatear fecha
     */
    public function formatDate(\DateTime $date): string
    {
        return $date->format($this->dateFormat);
    }

    /**
     * Formatear hora
     */
    public function formatTime(\DateTime $time): string
    {
        return $time->format($this->timeFormat);
    }

    /**
     * Formatear fecha y hora
     */
    public function formatDateTime(\DateTime $datetime): string
    {
        return $datetime->format($this->datetimeFormat);
    }

    /**
     * Formatear número
     */
    public function formatNumber(float $number, int $decimals = 2): string
    {
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Formatear moneda
     */
    public function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $formattedAmount = $this->formatNumber($amount, 2);
        return str_replace('0,0.00', $formattedAmount, $this->currencyFormat);
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'system_locale' => $this->systemLocale,
            'direction' => $this->direction,
            'date_format' => $this->dateFormat,
            'time_format' => $this->timeFormat,
            'datetime_format' => $this->datetimeFormat,
            'number_format' => $this->numberFormat,
            'currency_format' => $this->currencyFormat,
            'plural_rules' => $this->pluralRules,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convertir a JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convertir a string
     */
    public function __toString(): string
    {
        return $this->code;
    }
}
