<?php

namespace App\Mcp;

/**
 * A small JSON Schema validator covering what tool input schemas need:
 * type, enum, required, properties, additionalProperties, items, string
 * length and pattern, "date" format, numeric bounds, array length.
 *
 * Deliberately dependency-free. If your schemas outgrow it, swap in
 * opis/json-schema or justinrainbow/json-schema behind the same method.
 */
final class SchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return list<string> Human-readable problems, empty when valid.
     */
    public function validate(array $schema, mixed $value, string $path = 'arguments'): array
    {
        $type = $schema['type'] ?? null;

        if ($type !== null && ! $this->matchesType($type, $value)) {
            $expected = is_array($type) ? implode(' or ', $type) : $type;

            return ["{$path} must be of type {$expected}"];
        }

        $errors = [];

        if (array_key_exists('enum', $schema) && ! in_array($value, $schema['enum'], true)) {
            $allowed = implode(', ', array_map(fn ($v) => is_bool($v) ? var_export($v, true) : (string) $v, $schema['enum']));
            $errors[] = "{$path} must be one of: {$allowed}";
        }

        if (is_string($value)) {
            $errors = [...$errors, ...$this->validateString($schema, $value, $path)];
        }

        if (is_int($value) || is_float($value)) {
            $errors = [...$errors, ...$this->validateNumber($schema, $value, $path)];
        }

        if (is_array($value) && ($type === 'object' || isset($schema['properties']))) {
            $errors = [...$errors, ...$this->validateObject($schema, $value, $path)];
        }

        if (is_array($value) && $type === 'array') {
            $errors = [...$errors, ...$this->validateArray($schema, $value, $path)];
        }

        return $errors;
    }

    private function matchesType(string|array $type, mixed $value): bool
    {
        foreach ((array) $type as $candidate) {
            $ok = match ($candidate) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
                default => false,
            };

            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function validateString(array $schema, string $value, string $path): array
    {
        $errors = [];
        $length = mb_strlen($value);

        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            $errors[] = "{$path} must be at least {$schema['minLength']} characters";
        }

        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            $errors[] = "{$path} must be at most {$schema['maxLength']} characters";
        }

        if (isset($schema['pattern']) && ! preg_match('~'.$schema['pattern'].'~u', $value)) {
            $errors[] = "{$path} does not match the expected format";
        }

        if (($schema['format'] ?? null) === 'date' && ! $this->isDate($value)) {
            $errors[] = "{$path} must be a calendar date in YYYY-MM-DD format";
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function validateNumber(array $schema, int|float $value, string $path): array
    {
        $errors = [];

        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            $errors[] = "{$path} must be at least {$schema['minimum']}";
        }

        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            $errors[] = "{$path} must be at most {$schema['maximum']}";
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function validateObject(array $schema, array $value, string $path): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];

        foreach ($schema['required'] ?? [] as $required) {
            if (! array_key_exists($required, $value)) {
                $errors[] = "{$path}.{$required} is required";
            }
        }

        foreach ($value as $key => $item) {
            if (isset($properties[$key])) {
                $errors = [...$errors, ...$this->validate($properties[$key], $item, "{$path}.{$key}")];
            } elseif (($schema['additionalProperties'] ?? true) === false) {
                $errors[] = "{$path}.{$key} is not an accepted argument";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<mixed>  $value
     * @return list<string>
     */
    private function validateArray(array $schema, array $value, string $path): array
    {
        $errors = [];
        $count = count($value);

        if (isset($schema['minItems']) && $count < $schema['minItems']) {
            $errors[] = "{$path} must contain at least {$schema['minItems']} items";
        }

        if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
            $errors[] = "{$path} must contain at most {$schema['maxItems']} items";
        }

        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $errors = [...$errors, ...$this->validate($schema['items'], $item, "{$path}[{$index}]")];
            }
        }

        return $errors;
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
