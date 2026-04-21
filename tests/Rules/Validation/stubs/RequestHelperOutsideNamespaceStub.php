<?php declare(strict_types=1);

namespace Vendor\Package;

final class RequestHelperOutsideNamespaceStub
{
    public function __invoke(): mixed
    {
        return request('foo');
    }
}
