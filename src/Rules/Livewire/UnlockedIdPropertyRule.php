<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Livewire;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Override;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * The browser can set any public property of a Livewire component or form
 * object that is not `#[Locked]`. A public id property is how a user points a
 * component at a record they cannot see: change the id in the request payload
 * and the next action runs against it.
 *
 * An id the user picks on purpose (a select, a checkbox list) is fine when the
 * server validates or scopes it; mark those with an ignore that says how.
 *
 * Only the class's own declarations are read, not properties a trait adds.
 *
 * Inert until a project lists the namespaces to check.
 *
 * @implements Rule<InClassNode>
 * @see UnlockedIdPropertyRuleTest
 */
final readonly class UnlockedIdPropertyRule implements Rule
{
    use ChecksNamespace;

    /**
     * Strings, not ::class: livewire/livewire is not a dependency of this package.
     */
    private const array LIVEWIRE_CLASSES = [
        'Livewire\Component',
        'Livewire\Features\SupportFormObjects\Form',
    ];

    private const string LOCKED_ATTRIBUTE = 'Livewire\Attributes\Locked';

    private const string ID_NAME_PATTERN = '/(^(id|ids|uuid|uuids)|Id|Ids|Uuid|Uuids|_id|_ids|_uuid|_uuids)$/';

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param  InClassNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->namespaceStartsWithAny($scope, $this->namespaces) || $this->namespaceStartsWithAny($scope, $this->excludeNamespaces)) {
            return [];
        }

        $classReflection = $node->getClassReflection();

        if (! $this->isLivewireClass($classReflection->getAncestors())) {
            return [];
        }

        $errors = [];

        foreach ($node->getOriginalNode()->getProperties() as $property) {
            foreach ($this->unlockedIdItems($property) as $item) {
                $errors[] = RuleErrorBuilder::message("Public Livewire property {$classReflection->getDisplayName()}::\${$item->name->toString()} holds an id but is not #[Locked], so the browser can change it.")
                    ->identifier('hihaho.livewire.unlockedIdProperty')
                    ->tip('Add #[Locked]. If the user picks this id on purpose and the server validates or scopes it, add @phpstan-ignore hihaho.livewire.unlockedIdProperty (reason).')
                    ->line($item->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<PropertyItem>
     */
    private function unlockedIdItems(Property $property): array
    {
        if (! $this->isClientWritable($property)) {
            return [];
        }

        return array_values(array_filter(
            $property->props,
            static fn (PropertyItem $item): bool => preg_match(self::ID_NAME_PATTERN, $item->name->toString()) === 1,
        ));
    }

    /**
     * @param  array<ClassReflection>  $ancestors
     */
    private function isLivewireClass(array $ancestors): bool
    {
        return array_any($ancestors, fn (ClassReflection $ancestor) => in_array($ancestor->getName(), self::LIVEWIRE_CLASSES, true));
    }

    /**
     * A bool flag cannot point at a record, and static properties are not part
     * of the component state Livewire sends to the browser.
     */
    private function isClientWritable(Property $property): bool
    {
        return $property->isPublic()
            && ! $property->isStatic()
            && ! $this->isBool($property)
            && ! $this->isLocked($property);
    }

    private function isBool(Property $property): bool
    {
        $type = $property->type instanceof NullableType ? $property->type->type : $property->type;

        return $type instanceof Identifier && $type->toLowerString() === 'bool';
    }

    private function isLocked(Property $property): bool
    {
        foreach ($property->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === self::LOCKED_ATTRIBUTE) {
                    return true;
                }
            }
        }

        return false;
    }
}
