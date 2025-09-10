<?php

namespace Faravel\Exceptions;

use Exception;

/**
 * Исключение, выбрасываемое при отказе в доступе. Обработчик
 * исключений Kernel должен вернуть ответ с кодом 403.
 */
class AuthorizationException extends Exception
{
    // Можно добавить свойства или методы при необходимости
}