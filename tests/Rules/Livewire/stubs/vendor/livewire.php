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
