<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

final class ProvidersNamespaceStub
{
    public function boot(): void
    {
        $callback = static fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->input('email') . $request->ip());

        $raw = request('direct');
        $facadeBool = RequestFacade::boolean('debug');

        unset($callback, $raw, $facadeBool);
    }
}
