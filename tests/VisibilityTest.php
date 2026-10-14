<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Tests;

use Alkin\FormSummaryBundle\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

final class VisibilityTest extends TestCase
{
    #[DataProvider('excludedTypes')]
    public function testExcludedTypesAndTheirDescendantsStayHidden(string $prefix): void
    {
        $parent = new FormView();
        $parent->vars['block_prefixes'] = ['form', $prefix, 'application_custom_type'];
        $parent->vars['summary'] = true;
        $child = new FormView($parent);
        $child->vars['block_prefixes'] = ['form', 'text'];
        $child->vars['summary'] = true;

        self::assertFalse(Visibility::isVisible($parent));
        self::assertFalse(Visibility::isVisible($child));
    }

    public static function excludedTypes(): iterable
    {
        foreach (['password', 'hidden', 'button', 'submit', 'reset', 'file', 'repeated'] as $prefix) {
            yield $prefix => [$prefix];
        }
    }

    public function testDisabledFormFieldsAreNotMistakenForExcludedFields(): void
    {
        $view = new FormView();
        $view->vars['block_prefixes'] = ['form', 'text'];
        $view->vars['disabled'] = true;
        self::assertTrue(Visibility::isVisible($view));
    }

    public function testAnExcludedGrandparentCannotBeOverriddenByAChild(): void
    {
        $root = new FormView();
        $root->vars['summary'] = false;
        $parent = new FormView($root);
        $child = new FormView($parent);
        $child->vars['summary'] = true;
        self::assertFalse(Visibility::isVisible($child));
    }
}
