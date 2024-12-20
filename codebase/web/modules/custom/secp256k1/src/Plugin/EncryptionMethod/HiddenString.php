<?php

namespace Drupal\secp256k1;

class HiddenString
{
    private $value;

    public function __construct(string $string)
    {
        $this->value = $string;
    }

    public function get(): string
    {
        return $this->value;
    }

    public function __destruct()
    {
        // 明示的に値を削除
        $this->value = null;
        unset($this->value);
    }
}