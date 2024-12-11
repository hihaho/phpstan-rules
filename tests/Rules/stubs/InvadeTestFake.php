<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use stdClass;

class InvadeTestFake extends TestCase
{
    public function fakeTest()
    {
        $obj = new stdClass();
        $invaded = invade($obj);
    }
}
