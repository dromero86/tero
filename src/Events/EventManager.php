<?php

namespace Tero\Events;

use Tero\Config\ConfigManager;
use Tero\Events\Contracts\EventManagerInterface;
use Tero\Events\Contracts\EventInterface;
use Tero\Events\Contracts\ListenerInterface;
use Tero\Events\Exceptions\EventException;

/**
 * EventManager - Gestor de eventos y listeners
 * 
 * @package Tero\Events
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class EventManager implements EventManagerInterface
{
    private ConfigManager $config;
    private array $listeners = [];
    private array $wildcards = [];
    private array $fired = [];
    private array $queued = [];
    private bool $wildcardEnabled = true;
    private bool $autoDiscovery = true;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->wildcardEnabled = $config->get('EVENTS_WILDCARD_ENABLED', true, 'bool');
        $this->autoDiscovery = $config->get('EVENTS_AUTO_DISCOVERY', true, 'bool');
        
        if ($this->autoDiscovery) {
            $this->discoverListeners();
        }
    }

    /**
     * Descubrir listeners automáticamente
     */
    private function discoverListeners(): void
    {
        $listenersPath = $this->config->get('LISTENER_PATH', 'app/Listeners');
        
        if (!is_dir($listenersPath)) {
            return;
        }
        
        $files = glob($listenersPath . '/*.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Listeners\\{$className}";
            
            if (class_exists($fullClassName)) {
                $this->registerListenerClass($fullClassName);
            }
        }
    }

    /**
     * Registrar clase de listener
     */
    private function registerListenerClass(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }
        
        $reflection = new \ReflectionClass($className);
        
        if (!$reflection->implementsInterface(ListenerInterface::class)) {
            return;
        }
        
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if (str_starts_with($method->getName(), 'handle')) {
                $eventName = $this->extractEventNameFromMethod($method->getName());
                $this->listen($eventName, [$className, $method->getName()]);
            }
        }
    }

    /**
     * Extraer nombre de evento del método
     */
    private function extractEventNameFromMethod(string $methodName): string
    {
        // handleUserCreated -> UserCreated
        $eventName = substr($methodName, 6); // Remove 'handle' prefix
        
        // Convertir a snake_case para eventos
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $eventName));
    }

    /**
     * Registrar listener para evento
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        if ($this->isWildcard($event)) {
            $this->wildcards[$event][] = [
                'listener' => $listener,
                'priority' => $priority
            ];
        } else {
            $this->listeners[$event][] = [
                'listener' => $listener,
                'priority' => $priority
            ];
        }
        
        // Ordenar por prioridad
        $this->sortListeners($event);
    }

    /**
     * Verificar si es wildcard
     */
    private function isWildcard(string $event): bool
    {
        return $this->wildcardEnabled && str_contains($event, '*');
    }

    /**
     * Ordenar listeners por prioridad
     */
    private function sortListeners(string $event): void
    {
        if (isset($this->listeners[$event])) {
            usort($this->listeners[$event], function($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
        }
        
        if (isset($this->wildcards[$event])) {
            usort($this->wildcards[$event], function($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
        }
    }

    /**
     * Disparar evento
     */
    public function fire(string $event, mixed $payload = [], bool $halt = false): mixed
    {
        $eventObject = $this->createEventObject($event, $payload);
        
        // Agregar a eventos disparados
        $this->fired[] = $event;
        
        // Obtener listeners
        $listeners = $this->getListeners($event);
        
        if (empty($listeners)) {
            return null;
        }
        
        $responses = [];
        
        foreach ($listeners as $listenerData) {
            $listener = $listenerData['listener'];
            
            try {
                $response = $this->callListener($listener, $eventObject);
                $responses[] = $response;
                
                // Si halt es true y hay respuesta, parar
                if ($halt && $response !== null) {
                    return $response;
                }
            } catch (\Throwable $e) {
                $this->handleListenerException($e, $event, $listener);
            }
        }
        
        return $halt ? null : $responses;
    }

    /**
     * Crear objeto de evento
     */
    private function createEventObject(string $event, mixed $payload): EventInterface
    {
        if (is_object($payload) && $payload instanceof EventInterface) {
            return $payload;
        }
        
        return new Event($event, $payload);
    }

    /**
     * Obtener listeners para evento
     */
    private function getListeners(string $event): array
    {
        $listeners = $this->listeners[$event] ?? [];
        
        // Agregar wildcards
        if ($this->wildcardEnabled) {
            foreach ($this->wildcards as $pattern => $wildcardListeners) {
                if ($this->matchesWildcard($event, $pattern)) {
                    $listeners = array_merge($listeners, $wildcardListeners);
                }
            }
        }
        
        // Ordenar por prioridad
        usort($listeners, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return $listeners;
    }

    /**
     * Verificar si evento coincide con wildcard
     */
    private function matchesWildcard(string $event, string $pattern): bool
    {
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match("/^{$pattern}$/", $event);
    }

    /**
     * Llamar listener
     */
    private function callListener(callable $listener, EventInterface $event): mixed
    {
        if (is_array($listener) && is_string($listener[0])) {
            // Clase::método
            $instance = new $listener[0]();
            return call_user_func([$instance, $listener[1]], $event);
        } elseif (is_array($listener) && is_object($listener[0])) {
            // $objeto->método
            return call_user_func($listener, $event);
        } else {
            // Función o closure
            return call_user_func($listener, $event);
        }
    }

    /**
     * Manejar excepción de listener
     */
    private function handleListenerException(\Throwable $e, string $event, callable $listener): void
    {
        $listenerName = $this->getListenerName($listener);
        
        error_log("Event listener exception in '{$event}' for '{$listenerName}': " . $e->getMessage());
        
        // Disparar evento de error
        $this->fire('event.listener.error', [
            'event' => $event,
            'listener' => $listenerName,
            'exception' => $e
        ]);
    }

    /**
     * Obtener nombre del listener
     */
    private function getListenerName(callable $listener): string
    {
        if (is_array($listener)) {
            if (is_string($listener[0])) {
                return $listener[0] . '::' . $listener[1];
            } else {
                return get_class($listener[0]) . '::' . $listener[1];
            }
        } elseif (is_string($listener)) {
            return $listener;
        } else {
            return 'Closure';
        }
    }

    /**
     * Encolar evento
     */
    public function queue(string $event, mixed $payload = [], int $delay = 0): void
    {
        $this->queued[] = [
            'event' => $event,
            'payload' => $payload,
            'delay' => $delay,
            'queued_at' => time()
        ];
    }

    /**
     * Procesar eventos encolados
     */
    public function processQueuedEvents(): int
    {
        $processed = 0;
        $now = time();
        
        foreach ($this->queued as $index => $queuedEvent) {
            if ($now >= $queuedEvent['queued_at'] + $queuedEvent['delay']) {
                $this->fire($queuedEvent['event'], $queuedEvent['payload']);
                unset($this->queued[$index]);
                $processed++;
            }
        }
        
        // Reindexar array
        $this->queued = array_values($this->queued);
        
        return $processed;
    }

    /**
     * Verificar si evento tiene listeners
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->getListeners($event));
    }

    /**
     * Obtener listeners de evento
     */
    public function getEventListeners(string $event): array
    {
        return $this->getListeners($event);
    }

    /**
     * Obtener todos los listeners
     */
    public function getAllListeners(): array
    {
        return array_merge($this->listeners, $this->wildcards);
    }

    /**
     * Obtener eventos disparados
     */
    public function getFiredEvents(): array
    {
        return $this->fired;
    }

    /**
     * Obtener eventos encolados
     */
    public function getQueuedEvents(): array
    {
        return $this->queued;
    }

    /**
     * Limpiar eventos disparados
     */
    public function clearFiredEvents(): void
    {
        $this->fired = [];
    }

    /**
     * Limpiar eventos encolados
     */
    public function clearQueuedEvents(): void
    {
        $this->queued = [];
    }

    /**
     * Remover listener
     */
    public function forget(string $event, callable $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$event]);
            unset($this->wildcards[$event]);
            return;
        }
        
        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_filter($this->listeners[$event], function($item) use ($listener) {
                return $item['listener'] !== $listener;
            });
        }
        
        if (isset($this->wildcards[$event])) {
            $this->wildcards[$event] = array_filter($this->wildcards[$event], function($item) use ($listener) {
                return $item['listener'] !== $listener;
            });
        }
    }

    /**
     * Remover todos los listeners
     */
    public function flush(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
        $this->fired = [];
        $this->queued = [];
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'wildcard_enabled' => $this->wildcardEnabled,
            'auto_discovery' => $this->autoDiscovery,
            'listeners_count' => count($this->listeners),
            'wildcards_count' => count($this->wildcards),
            'fired_events_count' => count($this->fired),
            'queued_events_count' => count($this->queued),
            'total_listeners' => array_sum(array_map('count', $this->listeners)) + array_sum(array_map('count', $this->wildcards))
        ];
    }
}
