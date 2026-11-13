<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

use RuntimeException;

/**
 * Steps of a batch can use what an earlier step produced: a value written "$step.field" stands for the field of the
 * answer of that step ("$folder.location_id"). The value is known only when the step has run.
 */
final class References
{
    private const PATTERN = '/^\$([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)$/';

    /** Whether a value is a reference, and to what: [step, field], or null. */
    public static function parse(mixed $value): ?array
    {
        return is_string($value) && preg_match(self::PATTERN, $value, $m) === 1 ? [$m[1], $m[2]] : null;
    }

    /**
     * The steps a value refers to, in order of appearance.
     *
     * @return list<string>
     */
    public static function steps(mixed $value): array
    {
        $found = [];
        self::walk($value, static function (mixed $leaf) use (&$found): void {
            $reference = self::parse($leaf);
            if ($reference !== null) {
                $found[] = $reference[0];
            }
        });

        return array_values(array_unique($found));
    }

    /**
     * The value with each reference replaced by what the step answered.
     *
     * @param array<string, array<string, mixed>> $answers Data of each step that has run, by step id
     *
     * @throws RuntimeException when a reference points to a step that has not run, or to a field it did not answer
     */
    public static function resolve(mixed $value, array $answers): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::resolve($item, $answers), $value);
        }
        $reference = self::parse($value);
        if ($reference === null) {
            return $value;
        }
        [$step, $field] = $reference;
        if (!isset($answers[$step])) {
            throw new RuntimeException(sprintf('"%s" refers to step "%s", which has not run.', $value, $step));
        }
        if (!array_key_exists($field, $answers[$step])) {
            throw new RuntimeException(sprintf('"%s": step "%s" did not answer a field "%s" (it answered: %s).', $value, $step, $field, implode(', ', array_keys($answers[$step])) ?: 'nothing'));
        }

        return $answers[$step][$field];
    }

    /**
     * The arguments with a stand-in of the right type wherever a reference is, so that they can be checked against the
     * schema before the step they depend on has run.
     *
     * @param array<string, mixed> $schema
     */
    public static function standIn(array $schema, mixed $value): mixed
    {
        if (self::parse($value) !== null) {
            return match ($schema['type'] ?? null) {
                'integer', 'number' => 1,
                'boolean' => true,
                'object' => [],
                'array' => [],
                default => 'x',
            };
        }
        if (!is_array($value)) {
            return $value;
        }
        if (($schema['type'] ?? null) === 'array' && isset($schema['items']) && is_array($schema['items'])) {
            return array_map(static fn (mixed $item): mixed => self::standIn($schema['items'], $item), $value);
        }
        $properties = (array) ($schema['properties'] ?? []);
        foreach ($value as $key => $item) {
            if (isset($properties[$key]) && is_array($properties[$key])) {
                $value[$key] = self::standIn($properties[$key], $item);
            } elseif (self::steps($item) !== []) {
                $value[$key] = self::standIn([], $item);
            }
        }

        return $value;
    }

    private static function walk(mixed $value, callable $visit): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::walk($item, $visit);
            }

            return;
        }
        $visit($value);
    }
}
