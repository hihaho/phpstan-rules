<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EloquentWithPropertyStub extends Model
{
    /**
     * @var list<string>
     */
    protected $with = ['chapters'];
}
