<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Presets;

use App\Presets\Http\UsersExport;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;
use Override;
use PhpParser\Node;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Spaze\PHPStan\Rules\Disallowed\Calls\MethodCalls;
use Spaze\PHPStan\Rules\Disallowed\Calls\NewCalls;
use Spaze\PHPStan\Rules\Disallowed\Calls\StaticCalls;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The shipped spaze/phpstan-disallowed-calls presets, run through the spaze
 * rules exactly as a consuming project would include them.
 *
 * @extends RuleTestCase<DelegatingRule>
 */
final class DisallowedCallPresetsTest extends RuleTestCase
{
    private const string STR_LIMIT_TIP = 'it cuts by display width; use mb_substr() for a length cap';

    private const string FILE_RESPONSE_MESSAGE = 'the response body is capped; store the file and redirect to a temporary signed URL';

    #[Override]
    protected function getRule(): Rule
    {
        $container = self::getContainer();

        /** @var list<Rule<Node>> $rules */
        $rules = [
            $container->getByType(MethodCalls::class),
            $container->getByType(StaticCalls::class),
            $container->getByType(NewCalls::class),
        ];

        return new DelegatingRule($rules);
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/presets.neon'];
    }

    #[Test]
    public function str_limit_preset_flags_both_limit_forms(): void
    {
        $this->analyse([__DIR__ . '/stubs/str-limit.php'], [
            ['Calling ' . Str::class . '::limit() is forbidden, ' . self::STR_LIMIT_TIP . '.', 11],
            ['Calling ' . Stringable::class . '::limit() is forbidden, ' . self::STR_LIMIT_TIP . '.', 12],
        ]);
    }

    #[Test]
    public function file_response_preset_flags_downloads_in_the_configured_paths(): void
    {
        $this->analyse([__DIR__ . '/stubs/Http/FileResponseController.php'], [
            [$this->fileResponse(ResponseFactory::class . '::download()'), 22],
            [$this->fileResponse(ResponseFactory::class . '::streamDownload()'), 23],
            [$this->fileResponse(ResponseFactory::class . '::file()'), 24],
            [$this->fileResponse(FilesystemAdapter::class . '::download()'), 25],
            [$this->fileResponse(FilesystemAdapter::class . '::response()'), 26],
            [$this->fileResponse(Storage::class . '::download()'), 27],
            [$this->fileResponse(Excel::class . '::download()'), 28],
            [$this->fileResponse(Exportable::class . '::download() (as ' . UsersExport::class . '::download())'), 29],
            [$this->fileResponse(StreamedResponse::class . '::__construct()'), 30],
            [$this->fileResponse(BinaryFileResponse::class . '::__construct()'), 31],
        ]);
    }

    #[Test]
    public function file_response_preset_ignores_code_outside_the_configured_paths(): void
    {
        $this->analyse([__DIR__ . '/stubs/Jobs/FileResponseJob.php'], []);
    }

    private function fileResponse(string $call): string
    {
        return "Calling {$call} is forbidden, " . self::FILE_RESPONSE_MESSAGE . '.';
    }
}
