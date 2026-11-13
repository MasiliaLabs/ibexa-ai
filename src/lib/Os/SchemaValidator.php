<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * Checks arguments against the part of JSON Schema the capabilities use: type, required, properties, enum, minimum,
 * maximum, minLength, maxLength, items. Unknown keys are accepted. Returns the problems, in words for the model.
 */
final class SchemaValidator
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    public static function validate(array $schema, mixed $value, string $path = 'arguments'): array
    {
        $errors = [];

        $type = $schema['type'] ?? null;
        if (is_string($type) && !self::isType($type, $value)) {
            return [sprintf('%s must be %s.', $path, self::article($type))];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = sprintf('%s must be one of: %s.', $path, implode(', ', array_map(static fn ($v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE), $schema['enum'])));
        }
        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = sprintf('%s must be at least %s.', $path, $schema['minimum']);
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = sprintf('%s must be at most %s.', $path, $schema['maximum']);
            }
        }
        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
                $errors[] = sprintf('%s must have at least %d characters.', $path, $schema['minLength']);
            }
            if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
                $errors[] = sprintf('%s must have at most %d characters.', $path, $schema['maxLength']);
            }
        }

        if (is_array($value) && ($type === 'object' || isset($schema['properties']))) {
            foreach ((array) ($schema['required'] ?? []) as $name) {
                if (!array_key_exists($name, $value)) {
                    $errors[] = sprintf('%s.%s is required.', $path, $name);
                }
            }
            foreach ((array) ($schema['properties'] ?? []) as $name => $propertySchema) {
                if (array_key_exists($name, $value) && is_array($propertySchema)) {
                    array_push($errors, ...self::validate($propertySchema, $value[$name], $path . '.' . $name));
                }
            }
        }

        if ($type === 'array' && is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                array_push($errors, ...self::validate($schema['items'], $item, sprintf('%s[%d]', $path, $index)));
            }
        }

        return $errors;
    }

    private static function isType(string $type, mixed $value): bool
    {
        return match ($type) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    private static function article(string $type): string
    {
        return match ($type) {
            'object' => 'an object',
            'array' => 'a list',
            'integer' => 'a whole number',
            'number' => 'a number',
            'boolean' => 'true or false',
            'string' => 'a string',
            default => $type,
        };
    }
}
