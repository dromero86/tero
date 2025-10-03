<?php

namespace Tero\I18n\Exceptions;

/**
 * TranslationException - Excepción del sistema de traducciones
 * 
 * @package Tero\I18n\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class TranslationException extends \Exception
{
    /**
     * Crear excepción de locale no encontrado
     */
    public static function localeNotFound(string $locale): self
    {
        return new self("Locale '{$locale}' not found");
    }

    /**
     * Crear excepción de traducción no encontrada
     */
    public static function translationNotFound(string $key, string $locale): self
    {
        return new self("Translation '{$key}' not found for locale '{$locale}'");
    }

    /**
     * Crear excepción de archivo de traducción no encontrado
     */
    public static function translationFileNotFound(string $file, string $locale): self
    {
        return new self("Translation file '{$file}' not found for locale '{$locale}'");
    }

    /**
     * Crear excepción de formato inválido
     */
    public static function invalidFormat(string $format, string $type): self
    {
        return new self("Invalid {$type} format: {$format}");
    }

    /**
     * Crear excepción de pluralización inválida
     */
    public static function invalidPluralization(string $locale, int $number): self
    {
        return new self("Invalid pluralization for locale '{$locale}' and number {$number}");
    }

    /**
     * Crear excepción de configuración faltante
     */
    public static function missingConfiguration(string $locale, string $config): self
    {
        return new self("Missing configuration for locale '{$locale}': {$config}");
    }

    /**
     * Crear excepción de encoding inválido
     */
    public static function invalidEncoding(string $string, string $encoding): self
    {
        return new self("Invalid encoding '{$encoding}' for string: {$string}");
    }

    /**
     * Crear excepción de timezone inválido
     */
    public static function invalidTimezone(string $timezone): self
    {
        return new self("Invalid timezone: {$timezone}");
    }
}
