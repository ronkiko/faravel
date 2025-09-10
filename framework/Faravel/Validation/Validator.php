<?php

namespace Faravel\Validation;

/**
 * Простейший валидатор данных. Поддерживает базовые правила: required,
 * string, numeric, email, min:n, max:n. Возвращает массив ошибок.
 */
class Validator
{
    /** @var array<string,mixed> */
    protected array $data;
    /** @var array<string,string|string[]> */
    protected array $rules;
    /** @var array<string,array<int,string>> */
    protected array $errors = [];

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->validate();
    }

    /**
     * Проверить, прошла ли валидация.
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Получить массив ошибок. Ключ — имя поля, значение — массив сообщений.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    protected function validate(): void
    {
        foreach ($this->rules as $attribute => $rules) {
            $value = $this->data[$attribute] ?? null;
            $rules = is_array($rules) ? $rules : explode('|', (string)$rules);
            foreach ($rules as $rule) {
                $this->applyRule($attribute, $value, $rule);
            }
        }
    }

    protected function applyRule(string $attribute, $value, string $rule): void
    {
        $params = [];
        if (str_contains($rule, ':')) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }
        $method = 'validate' . ucfirst(strtolower($rule));
        if (method_exists($this, $method)) {
            $this->$method($attribute, $value, $params);
        }
    }

    protected function addError(string $attribute, string $message): void
    {
        $this->errors[$attribute][] = $message;
    }

    /** Правило required */
    protected function validateRequired(string $attribute, $value, array $params): void
    {
        if ($value === null || $value === '') {
            $this->addError($attribute, "The {$attribute} field is required.");
        }
    }

    /** Правило string */
    protected function validateString(string $attribute, $value, array $params): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($attribute, "The {$attribute} must be a string.");
        }
    }

    /** Правило numeric */
    protected function validateNumeric(string $attribute, $value, array $params): void
    {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($attribute, "The {$attribute} must be numeric.");
        }
    }

    /** Правило email */
    protected function validateEmail(string $attribute, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($attribute, "The {$attribute} must be a valid email address.");
        }
    }

    /** Правило min */
    protected function validateMin(string $attribute, $value, array $params): void
    {
        $min = (int)($params[0] ?? 0);
        if ($value === null) {
            return;
        }
        if (is_numeric($value)) {
            if ($value < $min) {
                $this->addError($attribute, "The {$attribute} must be at least {$min}.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) < $min) {
                $this->addError($attribute, "The {$attribute} must be at least {$min} characters.");
            }
        }
    }

    /** Правило max */
    protected function validateMax(string $attribute, $value, array $params): void
    {
        $max = (int)($params[0] ?? 0);
        if ($value === null) {
            return;
        }
        if (is_numeric($value)) {
            if ($value > $max) {
                $this->addError($attribute, "The {$attribute} may not be greater than {$max}.");
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) > $max) {
                $this->addError($attribute, "The {$attribute} may not be greater than {$max} characters.");
            }
        }
    }
}