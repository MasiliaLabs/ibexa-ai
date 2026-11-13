<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * Models often write a number as text ("50"), a flag as "true", an object as a JSON string, or a list wrapped in an
 * object (`{"item": [...]}`, or keys "0", "1"…). A value that means
 * what the schema asks for, without doubt, is turned into it before it is checked; anything else is left as it is,
 * and the validation says what is wrong.
 */
final class SchemaCoercer
{
    /**
     * @param array<string, mixed> $schema
     */
    public static function coerce(array $schema, mixed $value): mixed
    {
        $type = $schema['type'] ?? null;

        switch ($type) {
            case 'integer':
                if (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1) {
                    return (int) trim($value);
                }

                return is_float($value) && floor($value) === $value ? (int) $value : $value;
            case 'number':
                return is_string($value) && is_numeric($value) ? $value + 0 : $value;
            case 'boolean':
                if (is_string($value)) {
                    return match (strtolower(trim($value))) {
                        'true' => true,
                        'false' => false,
                        default => $value,
                    };
                }

                return $value;
            case 'string':
                return is_int($value) || is_float($value) ? (string) $value : $value;
            case 'object':
                $value = self::decoded($value);
                if (!is_array($value)) {
                    return $value;
                }
                foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
                    if (array_key_exists($name, $value) && is_array($property)) {
                        $value[$name] = self::coerce($property, $value[$name]);
                    }
                }

                return $value;
            case 'array':
                $value = self::asList(self::decoded($value));
                if (!is_array($value) || !isset($schema['items']) || !is_array($schema['items'])) {
                    return $value;
                }

                return array_map(static fn (mixed $item): mixed => self::coerce($schema['items'], $item), $value);
            default:
                return $value;
        }
    }

    /**
     * A list that arrives wrapped, as some models write it: `{"item": [...]}` or `{"0": ..., "1": ...}`.
     */
    private static function asList(mixed $value): mixed
    {
        if (!is_array($value) || array_is_list($value) || $value === []) {
            return $value;
        }
        if (count($value) === 1 && isset($value['item']) && is_array($value['item'])) {
            return array_is_list($value['item']) ? $value['item'] : [$value['item']];
        }
        $keys = array_keys($value);
        if ($keys === array_map('strval', range(0, count($value) - 1))) {
            return array_values($value);
        }

        return $value;
    }

    /** A JSON string for an object or a list is that object or list. */
    private static function decoded(mixed $value): mixed
    {
        if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['{', '['], true)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }
}
