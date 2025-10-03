<?php

namespace Tero\Views;

use Tero\Views\Contracts\ViewManagerInterface;

/**
 * View - Clase de vista individual
 * 
 * @package Tero\Views
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class View
{
    private ViewManagerInterface $viewManager;
    private string $view;
    private array $data;

    public function __construct(ViewManagerInterface $viewManager, string $view, array $data = [])
    {
        $this->viewManager = $viewManager;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Agregar datos a la vista
     */
    public function with(string $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        
        return $this;
    }

    /**
     * Obtener datos de la vista
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Obtener nombre de la vista
     */
    public function getName(): string
    {
        return $this->view;
    }

    /**
     * Renderizar vista
     */
    public function render(): string
    {
        return $this->viewManager->render($this->view, $this->data);
    }

    /**
     * Convertir a string
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
