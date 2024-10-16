<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PeopleControllerStub
{
    public function __invoke(Request $request)
    {
        return new JsonResponse([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->string('last_name'),
            'age' => $request->integer('age'),
            'has_children' => $request->boolean('has_children'),
            'children' => [
                'name' => $request->only(['name']),
            ],
            'email' => $request->safe()->email, // @phpstan-ignore method.notFound
            'city' => $request->get('city'),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([]);

        return new JsonResponse([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'age' => $data['age'],
            'has_children' => $data['has_children'],
            'children' => [
                'name' => $data['name'],
            ],
            'email' => $data['email'],
            'city' => $data['city'],
        ]);
    }
}
