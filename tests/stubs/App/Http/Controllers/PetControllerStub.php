<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Http\JsonResponse;

final class PetControllerStub
{
    public function __invoke(UserRequest $request)
    {
        return new JsonResponse([
            'data' => [
                'name' => $request->get('name'),
                'breed' => $request->safe()->str('breed'),
            ],
        ]);
    }
}
