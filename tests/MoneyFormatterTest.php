<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Tests;

use Alkin\FormSummaryBundle\ValueFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

final class MoneyFormatterTest extends TestCase
{
    public function testZeroAmountKeepsItsCurrencyPattern(): void
    {
        $view = new FormView();
        $view->vars['value'] = '0.00';
        $view->vars['money_pattern'] = 'EUR {{ widget }}';
        self::assertSame('EUR 0.00', (new ValueFormatter())->money($view));
    }

    public function testMissingAmountDoesNotDisplayOnlyACurrency(): void
    {
        $view = new FormView();
        $view->vars['value'] = '';
        $view->vars['money_pattern'] = 'EUR {{ widget }}';
        self::assertNull((new ValueFormatter())->money($view));
    }

    public function testInvalidPatternIsRejectedInsteadOfReturningAnArray(): void
    {
        $view = new FormView();
        $view->vars['value'] = '12.50';
        $view->vars['money_pattern'] = ['{{ widget }}'];
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('string money_pattern');
        (new ValueFormatter())->money($view);
    }
}
