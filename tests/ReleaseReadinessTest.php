<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Tests;

use Alkin\FormSummaryBundle\Form\Extension\SummaryTypeExtension;
use Alkin\FormSummaryBundle\SummaryRenderer;
use Alkin\FormSummaryBundle\Twig\FormSummaryExtension;
use Alkin\FormSummaryBundle\ValueFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class ReleaseReadinessTest extends TestCase
{
    private Environment $twig;
    private FormFactoryInterface $factory;
    private string $previousLocale;
    private array $themes = ['form_div_layout.html.twig', '@FormSummary/form_summary_layout.html.twig'];

    protected function setUp(): void
    {
        $this->previousLocale = \Locale::getDefault();
        \Locale::setDefault('en');
        $translator = new Translator('en');
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__).'/templates', 'FormSummary');
        $loader->addPath(\dirname((new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form');
        $this->twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => false]);
        $this->twig->addExtension(new FormExtension($translator));
        $this->twig->addExtension(new TranslationExtension($translator));
        $this->twig->addExtension(new FormSummaryExtension(new SummaryRenderer(new ValueFormatter())));
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn (): FormRenderer => new FormRenderer(new TwigRendererEngine($this->themes, $this->twig)),
        ]));
        $this->factory = Forms::createFormFactoryBuilder()->addTypeExtension(new SummaryTypeExtension())->getFormFactory();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    private function value(FormView $view, string $empty = '—'): string
    {
        return trim($this->twig->createTemplate('{{ form_summary_value(form, {summary_empty: empty}) }}')->render(['form' => $view, 'empty' => $empty]));
    }

    #[DataProvider('placeholders')]
    public function testPartialDatesRespectThePlaceholder(string $placeholder, string $escaped): void
    {
        $view = $this->factory->create(Type\DateType::class, new \DateTimeImmutable('2026-10-14'), [
            'input' => 'datetime_immutable', 'widget' => 'choice', 'format' => 'dd.MM.yyyy',
        ])->createView();
        $view['year']->vars['summary'] = false;
        self::assertSame('14.10.'.$escaped, $this->value($view, $placeholder));
    }

    #[DataProvider('placeholders')]
    public function testPartialTimesRespectThePlaceholder(string $placeholder, string $escaped): void
    {
        $view = $this->factory->create(Type\TimeType::class, new \DateTimeImmutable('2026-10-14 09:05:07'), [
            'input' => 'datetime_immutable', 'widget' => 'choice', 'with_seconds' => true,
        ])->createView();
        $view['minute']->vars['summary'] = false;
        self::assertSame('09:'.$escaped.':07', $this->value($view, $placeholder));
    }

    #[DataProvider('placeholders')]
    public function testPartialDatetimesRespectThePlaceholder(string $placeholder, string $escaped): void
    {
        $view = $this->factory->create(Type\DateTimeType::class, new \DateTimeImmutable('2026-10-14 09:05:07'), [
            'input' => 'datetime_immutable', 'widget' => 'choice', 'date_format' => 'dd.MM.yyyy',
        ])->createView();
        $view['time']->vars['summary'] = false;
        self::assertSame(trim('14.10.2026 '.$escaped), $this->value($view, $placeholder));
    }

    public static function placeholders(): iterable
    {
        yield 'text' => ['Not provided', 'Not provided'];
        yield 'HTML text' => ['<missing>', '&lt;missing&gt;'];
        yield 'empty string' => ['', ''];
    }

    public function testAnIncompleteSubmissionDoesNotLoseItsDateParts(): void
    {
        $form = $this->factory->create(Type\DateType::class, null, ['widget' => 'choice', 'format' => 'dd.MM.yyyy']);
        $form->submit(['day' => '14', 'month' => '10', 'year' => '']);
        self::assertFalse($form->isSynchronized());
        self::assertSame('14.10.Missing', $this->value($form->createView(), 'Missing'));
    }

    #[DataProvider('themeOrders')]
    public function testSummaryThemesNeverReplaceEditableWidgetBlocks(array $themes): void
    {
        $this->themes = $themes;
        $view = $this->factory->createNamed('note', Type\TextType::class, '<Visible>')->createView();
        self::assertSame('&lt;Visible&gt;', $this->value($view));
        $html = $this->twig->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);
        self::assertStringContainsString('form-control', $html);
        self::assertStringContainsString('&lt;Visible&gt;', $html);
    }

    public static function themeOrders(): iterable
    {
        yield 'summary last' => [['bootstrap_5_layout.html.twig', '@FormSummary/form_summary_layout.html.twig']];
        yield 'summary first' => [['@FormSummary/form_summary_layout.html.twig', 'bootstrap_5_layout.html.twig']];
    }

    public function testSummaryRenderingDoesNotReloadChoices(): void
    {
        $loads = 0;
        $view = $this->factory->create(Type\ChoiceType::class, 'ready', [
            'choice_loader' => new CallbackChoiceLoader(static function () use (&$loads): array {
                ++$loads;

                return ['Ready' => 'ready', 'Pending' => 'pending'];
            }),
        ])->createView();
        $before = $loads;
        self::assertGreaterThan(0, $before);
        self::assertSame('Ready', $this->value($view));
        self::assertSame('Ready', $this->value($view));
        self::assertSame($before, $loads);
    }

    public function testObjectChoicesUseLabelsWithoutStringifyingTheObjects(): void
    {
        $item = new SummaryChoice('Warehouse');
        $view = $this->factory->create(Type\ChoiceType::class, $item, [
            'choices' => [$item],
            'choice_label' => static fn (SummaryChoice $choice): string => $choice->label,
        ])->createView();
        self::assertSame('Warehouse', $this->value($view));
    }

    public function testACompoundDatetimePropagatesThePlaceholderToItsNestedDate(): void
    {
        $view = $this->factory->create(Type\DateTimeType::class, new \DateTimeImmutable('2026-10-14 09:05:07'), [
            'input' => 'datetime_immutable', 'widget' => 'choice', 'date_format' => 'dd.MM.yyyy',
        ])->createView();
        $view['date']['year']->vars['summary'] = false;
        self::assertSame('14.10.Hidden 09:05', $this->value($view, 'Hidden'));
    }

    public function testAnEmptyCompoundFieldUsesOnlyOnePlaceholder(): void
    {
        foreach ([Type\DateType::class, Type\TimeType::class, Type\DateTimeType::class] as $type) {
            $view = $this->factory->create($type, null, ['widget' => 'choice'])->createView();
            self::assertSame('Missing', $this->value($view, 'Missing'));
        }
    }

    public function testDefaultTemplatesEscapeEvenWhenApplicationAutoescapeIsDisabled(): void
    {
        $view = $this->factory->createNamed('note', Type\TextType::class, '<script>untrusted</script>', [
            'label' => '<b>Label</b>', 'label_html' => true,
        ])->createView();
        $html = $this->twig->createTemplate('{{ form_summary(form) }}')->render(['form' => $view]);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringContainsString('&lt;script&gt;untrusted&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;Label&lt;/b&gt;', $html);
    }
}

final readonly class SummaryChoice
{
    public function __construct(public string $label)
    {
    }

    public function __toString(): string
    {
        throw new \LogicException('Model objects must not be stringified by the summary.');
    }
}
