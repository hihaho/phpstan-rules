<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ModelWithoutWithPropertyStub extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['name'];

    protected $withCount = ['chapters'];
}
