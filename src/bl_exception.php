<?php

declare(strict_types=1);

final class Bl_Exception extends Exception
{
    // Bl_Exception Code number
    public const USER_ERROR = 1;
    public const CONFIG_ERROR = 2;
    public const SYSTEM_ERROR = 4;

    public function __construct(int $type, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $type, $previous);
    }

    public function bl_is_user_error(): bool
    {
        return $this->getCode() === self::USER_ERROR;
    }

    public function bl_is_config_error(): bool
    {
        return $this->getCode() === self::CONFIG_ERROR;
    }

    public function bl_is_system_error(): bool
    {
        return $this->getCode() === self::SYSTEM_ERROR;
    }

    public function bl_to_array(): array
    {
        return [
            'type' => $this->getCode(),
            'text' => $this->getMessage(),
            'previous' => $this->getPrevious()?->getMessage()
        ];
    }

    public function bl_full_chain_message(): string
    {
        $messages = [];
        $current = $this;
        while ($current !== null) {
            $messages[] = $current->getMessage();
            $current = $current->getPrevious();
        }
        return implode(' -> ', array_reverse($messages));
    }
}