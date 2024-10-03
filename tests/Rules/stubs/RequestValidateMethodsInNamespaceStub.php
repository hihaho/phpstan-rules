<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Http\Request;

final class RequestValidateMethodsInNamespaceStub
{
    public function __invoke(Request $request)
    {
        return response()->json([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->string('last_name'),
            'age' => $request->integer('age'),
            'has_children' => $request->boolean('has_children'),
            'children' => [
                'name' => $request->only(['name']),
            ],
            'email' => $request->safe()->email,
            'city' => $request->get('city'),
        ]);
    }

    public function show(UserRequest $request)
    {
        return response()->json([
            'first_name' => $request->safe()->string('first_name'),
            'last_name' => $request->safe()->string('last_name'),
            'age' => $request->safe()->integer('age'),
            'has_children' => $request->safe()->boolean('has_children'),
            'children' => [
                'name' => $request->safe()->only(['name']),
            ],
            'email' => $request->safe()->string('email'),
            'city' => $request->safe()->string('city'),
        ]);
    }
}
