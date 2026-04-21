<?php declare(strict_types=1);

namespace Vendor\Package;

use Illuminate\Http\Request;

final class VendorNamespaceStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        return [
            'a' => $request->input('a'),
            'b' => $request->all(),
        ];
    }
}
