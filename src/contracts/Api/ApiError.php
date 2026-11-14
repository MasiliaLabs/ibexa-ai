<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Api;

/**
 * Error envelope returned to the admin front: {"error": {"code", "message"}}.
 */
final readonly class ApiError
{
    public const ACCESS_DENIED = 'ACCESS_DENIED';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const NOT_FOUND = 'NOT_FOUND';
    public const UNSUPPORTED_FIELD_TYPE = 'UNSUPPORTED_FIELD_TYPE';
    public const INVALID_OUTPUT = 'INVALID_OUTPUT';
    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    public function __construct(
        public string $code,
        public string $message,
    ) {
    }

    public static function accessDenied(): self
    {
        return new self(self::ACCESS_DENIED, 'Access Denied');
    }

    public static function validation(string $message): self
    {
        return new self(self::VALIDATION_ERROR, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(self::NOT_FOUND, $message);
    }

    public static function unsupportedFieldType(string $fieldType): self
    {
        return new self(self::UNSUPPORTED_FIELD_TYPE, sprintf('Unsupported field type: %s', $fieldType));
    }

    public static function invalidOutput(string $message): self
    {
        return new self(self::INVALID_OUTPUT, $message);
    }

    public static function serviceUnavailable(string $message): self
    {
        return new self(self::SERVICE_UNAVAILABLE, $message);
    }

    /**
     * @return array{error: array{code: string, message: string}}
     */
    public function toArray(): array
    {
        return ['error' => ['code' => $this->code, 'message' => $this->message]];
    }
}
