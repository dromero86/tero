<?php

namespace Tero\Session\Contracts;

/**
 * SessionManagerInterface - Interfaz para el gestor de sesiones
 * 
 * @package Tero\Session\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface SessionManagerInterface
{
    /**
     * Iniciar sesión
     */
    public function start(): bool;

    /**
     * Verificar si sesión está iniciada
     */
    public function isStarted(): bool;

    /**
     * Obtener ID de sesión
     */
    public function getId(): string;

    /**
     * Regenerar ID de sesión
     */
    public function regenerateId(bool $deleteOldSession = true): bool;

    /**
     * Obtener valor de sesión
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Establecer valor de sesión
     */
    public function set(string $key, mixed $value): void;

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool;

    /**
     * Eliminar valor de sesión
     */
    public function remove(string $key): void;

    /**
     * Obtener todos los datos
     */
    public function all(): array;

    /**
     * Establecer múltiples valores
     */
    public function put(array $data): void;

    /**
     * Obtener y eliminar valor
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Flash data (disponible solo en la próxima request)
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Obtener flash data
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Verificar si flash data existe
     */
    public function hasFlash(string $key): bool;

    /**
     * Obtener todos los flash data
     */
    public function getFlashData(): array;

    /**
     * Limpiar flash data
     */
    public function clearFlash(): void;

    /**
     * Establecer usuario autenticado
     */
    public function setUser(int $userId): void;

    /**
     * Obtener ID de usuario
     */
    public function getUserId(): ?int;

    /**
     * Verificar si usuario está autenticado
     */
    public function isAuthenticated(): bool;

    /**
     * Cerrar sesión
     */
    public function logout(): void;

    /**
     * Limpiar toda la sesión
     */
    public function clear(): void;

    /**
     * Destruir sesión
     */
    public function destroy(): bool;

    /**
     * Guardar sesión
     */
    public function save(): void;
}
