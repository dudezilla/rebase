<?php
/* A reflective category tag for tests — read via Reflection in catalog.php.
   Echoes congruency's own heritage: it is itself a "tag" applied to a class. */
#[Attribute(Attribute::TARGET_CLASS)]
final class Category {
    public function __construct(public string $name, public string $blurb = '') {}
}
