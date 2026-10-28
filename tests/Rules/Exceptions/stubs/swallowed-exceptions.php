<?php declare(strict_types=1);

namespace App\Exceptions\Stubs;

use Exception;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SwallowedExceptionStubs
{
    public function emptyBody(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
        }
    }

    public function fallbackValue(): string
    {
        try {
            return $this->work();
        } catch (Exception $e) {
            return 'default';
        }
    }

    public function overwritesTheCaughtVariable(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            $e = null;
        }
    }

    public function callsFailedHookDirectly(object $job): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            $job->failed();
        }
    }

    public function multiTypeCatch(): void
    {
        try {
            $this->work();
        } catch (RuntimeException|Throwable) {
        }
    }

    public function narrowCatch(): void
    {
        try {
            $this->work();
        } catch (InvalidArgumentException) {
        }
    }

    public function reports(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function rethrows(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            throw new RuntimeException('wrapped', previous: $e);
        }
    }

    public function logsThroughFacade(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            Log::warning('work failed');
        }
    }

    public function logsThroughHelper(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            logger()->warning('work failed');
        }
    }

    public function aborts(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            abort_if(true, 500);
        }
    }

    public function failsTheJob(object $job): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            $job->fail();
        }
    }

    public function releasesTheJob(object $job): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            $job?->release(30);
        }
    }

    public function passesTheExceptionToAHelper(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            $this->notify($e);
        }
    }

    public function readsTheExceptionInAClosure(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            $this->defer(fn () => $e->getMessage());
        }
    }

    public function isReachable(): bool
    {
        try {
            $this->work();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function isolateWork(): bool
    {
        try {
            $this->work();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function tryParse(string $value): ?int
    {
        try {
            return (int) $this->work($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function tryParseWithoutNull(string $value): int
    {
        try {
            return (int) $this->work($value);
        } catch (Throwable) {
            return 0;
        }
    }

    public function denies(): AuthorizationResponse
    {
        try {
            $this->work();

            return AuthorizationResponse::allow();
        } catch (Throwable) {
            return AuthorizationResponse::deny('Not allowed.');
        }
    }

    public function validationRule(): void
    {
        $rule = function (string $attribute, mixed $value, \Closure $fail): void {
            try {
                $this->work();
            } catch (Throwable) {
                $fail('The value is invalid.');
            }
        };
    }

    public function nestedInsideReportingCatch(): void
    {
        try {
            $this->work();
        } catch (Throwable $e) {
            report($e);

            try {
                $this->work();
            } catch (Throwable) {
            }
        }
    }

    public function nestedReportDoesNotCoverOuter(): void
    {
        try {
            $this->work();
        } catch (Throwable) {
            try {
                $this->work();
            } catch (Throwable $inner) {
                report($inner);
            }
        }
    }

    private function work(string $value = ''): string
    {
        return $value;
    }

    private function notify(Throwable $e): void {}

    private function defer(\Closure $callback): void {}
}
