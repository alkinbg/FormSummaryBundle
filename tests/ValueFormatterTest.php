<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Tests;

use Alkin\FormSummaryBundle\ValueFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\TranslatableMessage;
use Twig\Markup;

final class ValueFormatterTest extends TestCase
{
    #[DataProvider('supportedValues')]
    public function testNormalizationPreservesTheExactValue(mixed $value): void
    {
        self::assertSame($value, (new ValueFormatter())->normalize($value));
    }

    public static function supportedValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'zero string' => ['0'];
        yield 'zero integer' => [0];
        yield 'zero float' => [0.0];
        yield 'false' => [false];
        yield 'true' => [true];
        yield 'text' => ['<b>Untrusted text</b>'];
        yield 'translatable' => [new TranslatableMessage('message')];
    }

    #[DataProvider('unsupportedValues')]
    public function testUnsupportedValuesAreNotAutomaticallySerialized(mixed $value): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Provide a scalar summary_value');
        (new ValueFormatter())->normalize($value);
    }

    public static function unsupportedValues(): iterable
    {
        yield 'array' => [['secret' => 'private-value']];
        yield 'object' => [(object) ['secret' => 'private-value']];
        yield 'date object' => [new \DateTimeImmutable('2026-10-14')];
        yield 'raw markup' => [new Markup('<script>unsafe</script>', 'UTF-8')];
    }

    public function testErrorMessagesDoNotIncludeTheRejectedData(): void
    {
        try {
            (new ValueFormatter())->normalize(['api_key' => 'private-api-value']);
            self::fail('Arrays must be rejected.');
        } catch (\UnexpectedValueException $exception) {
            self::assertStringNotContainsString('private-api-value', $exception->getMessage());
            self::assertStringNotContainsString('api_key', $exception->getMessage());
        }
    }

    public function testGroupedPreferredChoicesAreDeduplicatedWithoutDroppingEmptyOrZeroValues(): void
    {
        $empty = new ChoiceView('empty-model', '', 'Empty choice');
        $zero = new ChoiceView(0, '0', 'Zero choice');
        $other = new ChoiceView(1, '1', 'Unselected choice');
        $view = new FormView();
        $view->vars['value'] = ['', '0'];
        $view->vars['preferred_choices'] = [$zero];
        $view->vars['choices'] = [new ChoiceGroupView('Group', [$empty, $zero, $other])];

        self::assertSame([$zero, $empty], (new ValueFormatter())->choices($view));
    }

    public function testCompoundDateDoesNotReadAnExcludedChild(): void
    {
        $date = new FormView();
        $date->vars['compound'] = true;
        $date->vars['date_pattern'] = '{{ day }}.{{ month }}.{{ year }}';
        foreach (['year' => '2026', 'month' => '10', 'day' => '14'] as $name => $value) {
            $child = new FormView($date);
            $child->vars['value'] = $value;
            $child->vars['summary'] = 'year' !== $name;
            $date->children[$name] = $child;
        }

        self::assertSame('14.10.—', (new ValueFormatter())->date($date));
    }

    public function testEmptyCompoundDateAndTimeStayEmpty(): void
    {
        $view = new FormView();
        $view->vars['compound'] = true;
        $formatter = new ValueFormatter();
        self::assertNull($formatter->date($view));
        self::assertNull($formatter->time($view));
        self::assertNull($formatter->dateTime($view));
    }
}
