<?php declare(strict_types=1);

namespace App;

final class UserValueObject
{
    public function dump(): string
    {
        return 'domain value';
    }
}

namespace App;

use Illuminate\Support\Collection;

final class UnionReceiverChainedDumpStub
{
    /**
     * $value may be a Laravel Collection (dump() from Illuminate\*) or a user
     * value object (dump() declared on itself). The rule must flag because at
     * least one union member resolves the method to an Illuminate-declared
     * debug helper.
     *
     * @param  Collection<int, string>|UserValueObject  $value
     */
    public function test(Collection|UserValueObject $value): void
    {
        $value->dump();
    }
}
