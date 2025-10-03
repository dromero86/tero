<?php

namespace Tero\Data;

use Tero\Data\Contracts\DatasetInterface;
use Tero\Data\Exceptions\DatasetException;

/**
 * Dataset - Sistema de mapeo de datos DB-to-View
 * 
 * @package Tero\Data
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class Dataset implements DatasetInterface, \ArrayAccess, \Iterator, \Countable
{
    private array $data = [];
    private array $original = [];
    private array $casts = [];
    private array $hidden = [];
    private array $visible = [];
    private array $appends = [];
    private bool $exists = false;
    private string $primaryKey = 'id';
    private mixed $primaryKeyValue = null;

    public function __construct(array $data = [], bool $exists = false)
    {
        $this->data = $data;
        $this->original = $data;
        $this->exists = $exists;
        
        if (isset($data[$this->primaryKey])) {
            $this->primaryKeyValue = $data[$this->primaryKey];
        }
    }

    /**
     * Crear nueva instancia
     */
    public static function make(array $data = [], bool $exists = false): self
    {
        return new self($data, $exists);
    }

    /**
     * Crear desde array
     */
    public static function fromArray(array $data): self
    {
        return new self($data, true);
    }

    /**
     * Crear colección desde array de arrays
     */
    public static function collection(array $items): DatasetCollection
    {
        $collection = new DatasetCollection();
        
        foreach ($items as $item) {
            $collection->push(new self($item, true));
        }
        
        return $collection;
    }

    /**
     * Obtener atributo
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Establecer atributo
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Verificar si atributo existe
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Eliminar atributo
     */
    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Obtener todos los datos
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Obtener datos visibles
     */
    public function toArray(): array
    {
        $data = $this->data;
        
        // Aplicar casts
        $data = $this->applyCasts($data);
        
        // Ocultar campos
        if (!empty($this->hidden)) {
            $data = array_diff_key($data, array_flip($this->hidden));
        }
        
        // Mostrar solo campos visibles
        if (!empty($this->visible)) {
            $data = array_intersect_key($data, array_flip($this->visible));
        }
        
        // Agregar campos calculados
        foreach ($this->appends as $attribute) {
            $data[$attribute] = $this->getAttribute($attribute);
        }
        
        return $data;
    }

    /**
     * Convertir a JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Aplicar casts
     */
    private function applyCasts(array $data): array
    {
        foreach ($this->casts as $key => $cast) {
            if (isset($data[$key])) {
                $data[$key] = $this->castAttribute($data[$key], $cast);
            }
        }
        
        return $data;
    }

    /**
     * Cast atributo
     */
    private function castAttribute(mixed $value, string $cast): mixed
    {
        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : (array) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'date' => $this->castDate($value),
            'datetime' => $this->castDateTime($value),
            'timestamp' => $this->castTimestamp($value),
            default => $value
        };
    }

    /**
     * Cast a fecha
     */
    private function castDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        if (is_numeric($value)) {
            return date('Y-m-d', $value);
        }
        
        return date('Y-m-d', strtotime($value));
    }

    /**
     * Cast a datetime
     */
    private function castDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', $value);
        }
        
        return date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * Cast a timestamp
     */
    private function castTimestamp(mixed $value): ?int
    {
        if (empty($value)) {
            return null;
        }
        
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        return strtotime($value);
    }

    /**
     * Obtener atributo (para campos calculados)
     */
    public function getAttribute(string $key): mixed
    {
        $method = 'get' . ucfirst($key) . 'Attribute';
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        
        return $this->get($key);
    }

    /**
     * Establecer casts
     */
    public function setCasts(array $casts): self
    {
        $this->casts = $casts;
        return $this;
    }

    /**
     * Establecer campos ocultos
     */
    public function setHidden(array $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Establecer campos visibles
     */
    public function setVisible(array $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Establecer campos calculados
     */
    public function setAppends(array $appends): self
    {
        $this->appends = $appends;
        return $this;
    }

    /**
     * Verificar si el modelo existe
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Obtener clave primaria
     */
    public function getKey(): mixed
    {
        return $this->primaryKeyValue;
    }

    /**
     * Obtener nombre de clave primaria
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Establecer clave primaria
     */
    public function setKeyName(string $key): self
    {
        $this->primaryKey = $key;
        
        if (isset($this->data[$key])) {
            $this->primaryKeyValue = $this->data[$key];
        }
        
        return $this;
    }

    /**
     * Obtener datos originales
     */
    public function getOriginal(string $key = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }
        
        return $this->original[$key] ?? null;
    }

    /**
     * Verificar si ha cambiado
     */
    public function isDirty(string $key = null): bool
    {
        if ($key === null) {
            return $this->data !== $this->original;
        }
        
        return ($this->data[$key] ?? null) !== ($this->original[$key] ?? null);
    }

    /**
     * Obtener cambios
     */
    public function getChanges(): array
    {
        $changes = [];
        
        foreach ($this->data as $key => $value) {
            if (($this->original[$key] ?? null) !== $value) {
                $changes[$key] = $value;
            }
        }
        
        return $changes;
    }

    /**
     * Sincronizar con original
     */
    public function syncOriginal(): self
    {
        $this->original = $this->data;
        return $this;
    }

    /**
     * Filtrar datos
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->data, $callback, ARRAY_FILTER_USE_BOTH);
        return new self($filtered, $this->exists);
    }

    /**
     * Mapear datos
     */
    public function map(callable $callback): self
    {
        $mapped = array_map($callback, $this->data, array_keys($this->data));
        return new self($mapped, $this->exists);
    }

    /**
     * Obtener solo campos específicos
     */
    public function only(array $keys): self
    {
        $data = [];
        
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                $data[$key] = $this->data[$key];
            }
        }
        
        return new self($data, $this->exists);
    }

    /**
     * Obtener todos excepto campos específicos
     */
    public function except(array $keys): self
    {
        $data = $this->data;
        
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        
        return new self($data, $this->exists);
    }

    /**
     * Merge con otros datos
     */
    public function merge(array $data): self
    {
        $merged = array_merge($this->data, $data);
        return new self($merged, $this->exists);
    }

    /**
     * ArrayAccess: offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess: offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess: offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * ArrayAccess: offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * Iterator: current
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * Iterator: key
     */
    public function key(): mixed
    {
        return key($this->data);
    }

    /**
     * Iterator: next
     */
    public function next(): void
    {
        next($this->data);
    }

    /**
     * Iterator: rewind
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /**
     * Iterator: valid
     */
    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    /**
     * Countable: count
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Convertir a string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Obtener atributo mágico
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Establecer atributo mágico
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Verificar si atributo existe mágico
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Eliminar atributo mágico
     */
    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'data_count' => count($this->data),
            'original_count' => count($this->original),
            'casts_count' => count($this->casts),
            'hidden_count' => count($this->hidden),
            'visible_count' => count($this->visible),
            'appends_count' => count($this->appends),
            'exists' => $this->exists,
            'primary_key' => $this->primaryKey,
            'primary_key_value' => $this->primaryKeyValue,
            'is_dirty' => $this->isDirty(),
            'changes_count' => count($this->getChanges())
        ];
    }
}
