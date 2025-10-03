<?php

namespace Tero\Events\Contracts;

/**
 * ListenerInterface - Interfaz para listeners de eventos
 * 
 * @package Tero\Events\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface ListenerInterface
{
    /**
     * Manejar evento
     */
    public function handle(EventInterface $event): mixed;
}
