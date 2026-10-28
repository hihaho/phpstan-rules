<?php declare(strict_types=1);

namespace Livewire\Attributes;

use Attribute;

/**
 * Minimal stand-in for livewire/livewire, with the classes the rule reads.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Locked {}

namespace Livewire\Features\SupportFormObjects;

abstract class Form {}

namespace Livewire;

abstract class Component {}

abstract class Form extends Features\SupportFormObjects\Form {}

namespace Livewire\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Url {}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Validate
{
    public function __construct(mixed $rule = null) {}
}

namespace App\Attributes;

/**
 * Same short name as Livewire's attribute, so only the full name may count.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Locked {}
