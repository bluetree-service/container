<?php

namespace Test;

class SerializeFail
{
    public function __serialize(): array
    {
        throw new \Laminas\Serializer\Exception\RuntimeException('test exception');
    }

    public function __unserialize($string): void
    {
        
    }
}
