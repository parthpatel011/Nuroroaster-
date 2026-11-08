<?php

declare(strict_types=1);

namespace App\Domain\Rules\Contracts;

/**
 * A pure roster rule. Evaluators must not touch the database or the clock.
 * Configurable packs default to warning. BR-01 and BR-03 stay hard integrity
 * rules in the database and are not rows in rule_set_rules.
 */
interface Rule
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $params
     * @return list<Finding>
     */
    public function evaluate(array $context, array $params): array;
}
