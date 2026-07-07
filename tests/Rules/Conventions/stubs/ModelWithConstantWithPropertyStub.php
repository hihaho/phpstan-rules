<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ModelWithConstantWithPropertyStub extends Model
{
    /**
     * @var list<string>
     */
    private const array DEFAULT_RELATIONS = ['chapters'];

    /**
     * @var list<string>
     */
    protected $with = self::DEFAULT_RELATIONS;
}
