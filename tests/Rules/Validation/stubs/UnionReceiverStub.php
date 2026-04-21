<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class UnionReceiverOther
{
    public function input(string $key): string
    {
        return $key;
    }
}

final class UnionReceiverStub
{
    public function test(Request|UnionReceiverOther $receiver): mixed
    {
        return $receiver->input('key');
    }
}
