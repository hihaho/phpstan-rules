<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\ReturnTypes;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Override;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Types Collection::all() as list<TValue> when the receiver is a `->values()` call. Laravel types
 * values() as static<int, TValue> and all() as array<TKey, TValue>, neither of which carries the
 * list marker, so `->values()->all()` degrades to array<int, TValue>. That loses the JSON-array
 * guarantee — a non-list array encodes as a JS object — and forces array_values() wrappers around
 * otherwise-clean collection chains. This restores the list type.
 *
 * Two guards keep it sound:
 *  - Detection is syntactic: the receiver must be a direct `->values()` call, so the rule only
 *    fires where it can prove the re-key happened. A chain split across variables is not narrowed
 *    (it fails safe rather than guessing).
 *  - The receiver must be a Support\Collection or LazyCollection (or subclass) — the trees whose
 *    values() re-keys to a 0-based list per its documented contract. Subclasses are included on
 *    purpose so Eloquent collections (which inherit values() unchanged) benefit; the narrowing
 *    relies on that contract rather than re-proving it, so a subclass that deliberately overrides
 *    values() to break it is out of scope. A bare Enumerable or a custom implementation with
 *    unknown key semantics is never narrowed.
 *
 * Only values() is handled: it is the canonical, unconditional re-key-to-list method. flatten() is
 * also a list at runtime but Laravel does not reliably type its value type; collapse()/flatMap()
 * preserve the inner arrays' string keys via array_merge and are not lists at all.
 */
final readonly class CollectionListAllReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    #[Override]
    public function getClass(): string
    {
        return Enumerable::class;
    }

    #[Override]
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'all';
    }

    #[Override]
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $receiver = $methodCall->var;

        if (! $receiver instanceof MethodCall || ! $receiver->name instanceof Identifier) {
            return null;
        }

        if ($receiver->name->toString() !== 'values') {
            return null;
        }

        $receiverType = $scope->getType($receiver);

        $isKnownListCollection = (new ObjectType(Collection::class))->isSuperTypeOf($receiverType)->yes()
            || (new ObjectType(LazyCollection::class))->isSuperTypeOf($receiverType)->yes();

        if (! $isKnownListCollection) {
            return null;
        }

        return TypeCombinator::intersect(
            new ArrayType(new IntegerType(), $receiverType->getIterableValueType()),
            new AccessoryArrayListType(),
        );
    }
}
