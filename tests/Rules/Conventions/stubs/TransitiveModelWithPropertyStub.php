<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseInteractionModel extends Model {}

final class TransitiveModelWithPropertyStub extends BaseInteractionModel
{
    /**
     * @var list<string>
     */
    protected $with = ['chapters'];
}
