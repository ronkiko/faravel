<?php

namespace Faravel\Support\Facades;

use Faravel\Validation\Validator as ValidatorInstance;

/**
 * Фасад для валидатора. Позволяет создавать экземпляры валидатора через
 * статический метод make().
 */
class Validator
{
    public static function make(array $data, array $rules): ValidatorInstance
    {
        return ValidatorInstance::make($data, $rules);
    }
}