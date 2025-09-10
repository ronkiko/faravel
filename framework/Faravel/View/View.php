<?php // v0.4.1
/* framework/Faravel/View/View.php
Назначение: объект представления Faravel — инкапсулирует путь, данные и движок.
FIX: добавлены методы with() и getData() для поддержки view-композеров; это
позволяет безопасно дополнять данные конкретного экземпляра вида (например, $layout).
*/
namespace Faravel\View;

class View
{
    /** Absolute template path. */
    protected string $path;

    /** Mutable payload for this View instance only. */
    protected array $data;

    /** Rendering engine instance with get(string $path, array $data): string. */
    protected object $engine;

    /**
     * @param string $path   Absolute template path.
     * @param array<string,mixed> $data Initial payload (merged shared + local).
     * @param object $engine Rendering engine.
     */
    public function __construct(string $path, array $data, object $engine)
    {
        $this->path   = $path;
        $this->data   = $data;
        $this->engine = $engine;
    }

    /**
     * Add or replace variables in current View instance.
     * Accepts either (key, value) or associative array.
     *
     * @param string|array<string,mixed> $key
     * @param mixed $value
     * @return self
     *
     * @example
     *  $view->with('layout', $layout);
     *  $view->with(['title' => 'Home', 'brand' => 'Faravel']);
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[(string)$k] = $v;
            }
            return $this;
        }
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Get current payload for this View instance.
     *
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Render the view via engine.
     *
     * @return string
     */
    public function render(): string
    {
        return $this->engine->get($this->path, $this->data);
    }

    /**
     * Stringable view: return rendered output or a safe error string.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            return 'View render error: ' . $e->getMessage();
        }
    }
}
