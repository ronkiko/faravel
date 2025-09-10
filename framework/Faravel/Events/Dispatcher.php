<?php

namespace Faravel\Events;

/**
 * Простейший диспетчер событий. Позволяет регистрировать слушателей
 * и отправлять события.
 */
class Dispatcher
{
    /**
     * Список слушателей. Ключ — имя события, значение — массив из
     * ассоциативных структур с ключами 'callback' и 'priority'.
     * Допускается использовать специальное имя '*' для ловли всех
     * событий. События с более высоким приоритетом будут вызваны
     * первыми.
     *
     * @var array<string,array<int,array{callback: callable, priority: int}>>
     */
    protected array $listeners = [];

    /**
     * Зарегистрировать слушатель для указанного события.
     *
     * @param string $event
     * @param callable $listener
     */
    /**
     * Зарегистрировать слушатель для одного или нескольких событий.
     * Вы можете передать строку имени события, массив имён или
     * символ '*' для ловли всех событий. Второй параметр —
     * callback, который будет вызван при наступлении события.
     * Третий параметр определяет приоритет слушателя: слушатели
     * с более высоким приоритетом будут вызваны раньше.
     *
     * @param string|array<int,string> $event
     * @param callable $listener
     * @param int $priority
     */
    public function listen(string|array $event, callable $listener, int $priority = 0): void
    {
        $events = is_array($event) ? $event : [$event];
        foreach ($events as $name) {
            if (!isset($this->listeners[$name])) {
                $this->listeners[$name] = [];
            }
            $this->listeners[$name][] = [
                'callback' => $listener,
                'priority' => $priority,
            ];
        }
    }

    /**
     * Отправить событие слушателям. Событие передаётся как строка, а
     * дополнительная полезная нагрузка — в переменной $payload.
     *
     * @param string $event
     * @param mixed $payload
     */
    /**
     * Отправить событие слушателям. Метод собирает всех слушателей,
     * назначенных на конкретное событие и на глобальное '*' событие,
     * сортирует их по приоритету (от большего к меньшему) и
     * последовательно вызывает. Payload передаётся в
     * callback первым аргументом.
     *
     * @param string $event
     * @param mixed $payload
     */
    public function dispatch(string $event, mixed $payload = null): void
    {
        $toCall = [];
        // слушатели конкретного события
        if (isset($this->listeners[$event])) {
            $toCall = array_merge($toCall, $this->listeners[$event]);
        }
        // глобальные слушатели
        if (isset($this->listeners['*'])) {
            $toCall = array_merge($toCall, $this->listeners['*']);
        }
        // если есть слушатели, сортируем по приоритету
        if (!empty($toCall)) {
            usort($toCall, function ($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
            foreach ($toCall as $entry) {
                $callback = $entry['callback'];
                $callback($payload);
            }
        }
    }
}