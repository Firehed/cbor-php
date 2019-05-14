<?php
declare(strict_types=1);

use RangeException;

class UnassignedValueException extends RangeException
{
    public function __construct(int $value)
    {
        parent::__construct('Encountered unassigned value', $value);
    }
}
