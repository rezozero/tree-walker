<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests\Mock;

use Symfony\Component\Serializer\Attribute\Groups;

class Dummy
{
    public function __construct(
        #[Groups(['dummy'])]
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
