<?php

namespace Tero\Data;

use Tero\Data\Contracts\DatasetInterface;
use ArrayAccess;
use Iterator;
use Countable;

/**
 * DatasetCollection - Colección de datasets
 * 
 * @package Tero\Data
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatasetCollection implements ArrayAccess, Iterator, Countable
{
    private array $items = [];
    private int $position = 0;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Agregar item
     */
    public function push(DatasetInterface $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Agregar múltiples items
     */
    public function pushMany(array $items): self
    {
        foreach ($items as $item) {
            $this->push($item);
        }
        return $this;
    }

    /**
     * Obtener item por índice
     */
    public function get(int $index): ?DatasetInterface
    {
        return $this->items[$index] ?? null;
    }

    /**
     * Obtener primer item
     */
    public function first(): ?DatasetInterface
    {
        return $this->items[0] ?? null;
    }

    /**
     * Obtener último item
     */
    public function last(): ?DatasetInterface
    {
        return end($this->items) ?: null;
    }

    /**
     * Obtener todos los items
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return array_map(fn($item) => $item->toArray(), $this->items);
    }

    /**
     * Convertir a JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Filtrar colección
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->items, $callback);
        return new self(array_values($filtered));
    }

    /**
     * Mapear colección
     */
    public function map(callable $callback): self
    {
        $mapped = array_map($callback, $this->items);
        return new self($mapped);
    }

    /**
     * Reducir colección
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Obtener solo campos específicos
     */
    public function only(array $keys): self
    {
        return $this->map(fn($item) => $item->only($keys));
    }

    /**
     * Obtener todos excepto campos específicos
     */
    public function except(array $keys): self
    {
        return $this->map(fn($item) => $item->except($keys));
    }

    /**
     * Agrupar por campo
     */
    public function groupBy(string $key): array
    {
        $groups = [];
        
        foreach ($this->items as $item) {
            $groupKey = $item->get($key);
            $groups[$groupKey][] = $item;
        }
        
        return $groups;
    }

    /**
     * Ordenar por campo
     */
    public function sortBy(string $key, int $direction = SORT_ASC): self
    {
        $items = $this->items;
        
        usort($items, function($a, $b) use ($key, $direction) {
            $aValue = $a->get($key);
            $bValue = $b->get($key);
            
            if ($aValue == $bValue) {
                return 0;
            }
            
            $result = $aValue < $bValue ? -1 : 1;
            return $direction === SORT_ASC ? $result : -$result;
        });
        
        return new self($items);
    }

    /**
     * Obtener valores únicos por campo
     */
    public function unique(string $key): self
    {
        $seen = [];
        $unique = [];
        
        foreach ($this->items as $item) {
            $value = $item->get($key);
            if (!in_array($value, $seen)) {
                $seen[] = $value;
                $unique[] = $item;
            }
        }
        
        return new self($unique);
    }

    /**
     * Obtener slice de la colección
     */
    public function slice(int $offset, int $length = null): self
    {
        $sliced = array_slice($this->items, $offset, $length);
        return new self($sliced);
    }

    /**
     * Obtener chunk de la colección
     */
    public function chunk(int $size): array
    {
        $chunks = [];
        $items = array_chunk($this->items, $size);
        
        foreach ($items as $chunk) {
            $chunks[] = new self($chunk);
        }
        
        return $chunks;
    }

    /**
     * Combinar con otra colección
     */
    public function merge(DatasetCollection $collection): self
    {
        $merged = array_merge($this->items, $collection->all());
        return new self($merged);
    }

    /**
     * Obtener promedio de campo numérico
     */
    public function avg(string $key): float
    {
        if (empty($this->items)) {
            return 0;
        }
        
        $sum = $this->sum($key);
        return $sum / count($this->items);
    }

    /**
     * Obtener suma de campo numérico
     */
    public function sum(string $key): float
    {
        return $this->reduce(function($carry, $item) use ($key) {
            return $carry + (float) $item->get($key, 0);
        }, 0);
    }

    /**
     * Obtener máximo de campo
     */
    public function max(string $key): mixed
    {
        if (empty($this->items)) {
            return null;
        }
        
        $max = $this->items[0]->get($key);
        
        foreach ($this->items as $item) {
            $value = $item->get($key);
            if ($value > $max) {
                $max = $value;
            }
        }
        
        return $max;
    }

    /**
     * Obtener mínimo de campo
     */
    public function min(string $key): mixed
    {
        if (empty($this->items)) {
            return null;
        }
        
        $min = $this->items[0]->get($key);
        
        foreach ($this->items as $item) {
            $value = $item->get($key);
            if ($value < $min) {
                $min = $value;
            }
        }
        
        return $min;
    }

    /**
     * Obtener conteo por campo
     */
    public function countBy(string $key): array
    {
        $counts = [];
        
        foreach ($this->items as $item) {
            $value = $item->get($key);
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        
        return $counts;
    }

    /**
     * Verificar si colección está vacía
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Verificar si colección no está vacía
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Obtener tamaño de la colección
     */
    public function size(): int
    {
        return count($this->items);
    }

    /**
     * ArrayAccess: offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * ArrayAccess: offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * ArrayAccess: offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * ArrayAccess: offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Iterator: current
     */
    public function current(): mixed
    {
        return $this->items[$this->position] ?? null;
    }

    /**
     * Iterator: key
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Iterator: next
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Iterator: rewind
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Iterator: valid
     */
    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    /**
     * Countable: count
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Convertir a string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'items_count' => count($this->items),
            'position' => $this->position,
            'is_empty' => $this->isEmpty(),
            'size' => $this->size()
        ];
    }
}
