<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle;

use Symfony\Component\Form\FormView;

/** @internal */
final class Visibility
{
    private const EXCLUDED_PREFIXES = ['password', 'hidden', 'button', 'submit', 'reset', 'file', 'repeated'];

    private function __construct()
    {
    }

    public static function isVisible(FormView $view): bool
    {
        for ($current = $view; null !== $current; $current = $current->parent) {
            if (false === ($current->vars['summary'] ?? true)) {
                return false;
            }

            foreach ($current->vars['block_prefixes'] ?? [] as $prefix) {
                if (\in_array($prefix, self::EXCLUDED_PREFIXES, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
