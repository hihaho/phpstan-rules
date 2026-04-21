<?php declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResponsesNamespaceStub
{
    public function toResponse(Request $request): JsonResponse
    {
        return new JsonResponse([
            'email' => $request->input('email'),
            'remember' => $request->boolean('remember'),
        ]);
    }
}
