<?php

declare(strict_types=1);

namespace GaussDb\Compat;

/** Explicit binary input preserving embedded NUL bytes. */
final class BinaryValue
{
    /** @var string */
    public $bytes;

    /** Initialize the value object or adapter without changing database state. */
    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }
}
