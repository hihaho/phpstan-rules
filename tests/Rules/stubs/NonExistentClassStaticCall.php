<?php declare(strict_types=1);

namespace App;

use NonExistentFacadeAlias;

class NonExistentClassStaticCall
{
    public function test(): void
    {
        $result = NonExistentFacadeAlias::doSomething();
    }
}
