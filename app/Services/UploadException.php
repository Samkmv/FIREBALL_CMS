<?php
namespace App\Services;

/** Only localized, path-free messages are safe to return from an upload endpoint. */
final class UploadException extends \RuntimeException
{
    public function __construct(public readonly string $translationKey, array $values = [])
    {
        $message = function_exists('return_translation') ? return_translation($translationKey) : $translationKey;
        foreach ($values as $key => $value) $message = str_replace(':' . $key, (string)$value, $message);
        parent::__construct($message);
    }
}
