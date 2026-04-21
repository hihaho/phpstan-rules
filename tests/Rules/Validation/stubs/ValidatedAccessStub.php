<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class ValidatedAccessStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        return [
            'via_validate' => $validated['name'] ?? null,
            'via_safe' => $request->safe()->input('name'),
            'via_safe_only' => $request->safe()->only(['name']),
        ];
    }
}
