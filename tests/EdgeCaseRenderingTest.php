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
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class EdgeCaseRenderingTest extends TestCase
{
    private Environment $twig;
    private Translator $translator;
    private TwigArrayLoader $templates;
    private FormFactoryInterface $factory;
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->previousLocale = \Locale::getDefault();
        \Locale::setDefault('en');
        $this->translator = new Translator('en');
        $this->translator->addLoader('array', new ArrayLoader());
        $this->templates = new TwigArrayLoader();
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__).'/templates', 'FormSummary');
        $loader->addPath(\dirname((new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form');
        $this->twig = new Environment(new ChainLoader([$this->templates, $loader]), ['strict_variables' => true]);
        $this->twig->addExtension(new FormExtension($this->translator));
        $this->twig->addExtension(new TranslationExtension($this->translator));
        $this->twig->addExtension(new FormSummaryExtension(new SummaryRenderer(new ValueFormatter())));
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn (): FormRenderer => new FormRenderer(new TwigRendererEngine(['@FormSummary/form_summary_layout.html.twig'], $this->twig)),
        ]));
        $this->factory = Forms::createFormFactoryBuilder()->addTypeExtension(new SummaryTypeExtension())->getFormFactory();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    private function render(FormView $view, string $template = '{{ form_summary(form) }}', array $context = []): string
    {
        return $this->twig->createTemplate($template)->render(['form' => $view] + $context);
    }

    #[DataProvider('customCompoundValues')]
    public function testCompoundCustomValuesApplyToEverySummaryEntryPoint(mixed $value, string $expected): void
    {
        $view = $this->factory->createNamedBuilder('profile', Type\FormType::class, ['secret' => 'private-child-value'], ['summary_value' => $value])
            ->add('secret', Type\TextType::class)->getForm()->createView();

        foreach (['form_summary', 'form_summary_row', 'form_summary_value'] as $function) {
            $html = $this->render($view, '{{ '.$function.'(form) }}');
            self::assertStringContainsString($expected, $html);
            self::assertStringNotContainsString('private-child-value', $html);
        }
    }

    public static function customCompoundValues(): iterable
    {
        yield 'text' => ['<b>Replacement</b>', '&lt;b&gt;Replacement&lt;/b&gt;'];
        yield 'null' => [null, '—'];
        yield 'zero' => [0, '0'];
        yield 'false' => [false, 'No'];
    }

    public function testCompoundCustomCallbackIsLazyAndRunsOncePerSummary(): void
    {
        $calls = 0;
        $view = $this->factory->createBuilder(Type\FormType::class, ['name' => 'original'], [
            'summary_value' => static function (FormView $view) use (&$calls): string {
                ++$calls;

                return 'Summary of '.$view['name']->vars['value'];
            },
        ])->add('name', Type\TextType::class)->getForm()->createView();

        self::assertSame(0, $calls);
        self::assertStringContainsString('Summary of original', $this->render($view));
        self::assertSame(1, $calls);
        $this->render($view);
        self::assertSame(2, $calls);
    }

    #[DataProvider('expandedModes')]
    public function testExpandedChoicesDoNotExposeExcludedChildren(bool $multiple): void
    {
        $view = $this->factory->create(Type\ChoiceType::class, $multiple ? ['public', 'internal'] : 'internal', [
            'choices' => ['Public choice' => 'public', 'Internal choice' => 'internal'],
            'expanded' => true,
            'multiple' => $multiple,
        ])->createView();
        $excluded = false;
        foreach ($view as $child) {
            if ('internal' === $child->vars['value']) {
                $child->vars['summary'] = false;
                $excluded = true;
            }
        }

        self::assertTrue($excluded);
        $html = $this->render($view);
        self::assertStringNotContainsString('Internal choice', $html);
        self::assertStringContainsString($multiple ? 'Public choice' : '—', $html);
    }

    public static function expandedModes(): iterable
    {
        yield 'radios' => [false];
        yield 'checkboxes' => [true];
    }

    public function testCustomEmptyPlaceholderIsPropagatedAndEscaped(): void
    {
        $builder = $this->factory->createBuilder(Type\FormType::class, ['nested' => ['note' => null]]);
        $builder->add($builder->create('nested', Type\FormType::class)->add('note', Type\TextType::class));
        $html = $this->render($builder->getForm()->createView(), '{{ form_summary(form, {summary_empty: placeholder}) }}', ['placeholder' => '<b>Missing</b>']);

        self::assertStringContainsString('&lt;b&gt;Missing&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    #[DataProvider('invalidPlaceholders')]
    public function testNonTextPlaceholdersAreRejected(mixed $placeholder): void
    {
        $view = $this->factory->create(Type\TextType::class)->createView();
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('summary_empty must be a string');
        $this->render($view, '{{ form_summary_value(form, {summary_empty: placeholder}) }}', ['placeholder' => $placeholder]);
    }

    public static function invalidPlaceholders(): iterable
    {
        yield 'markup' => [new Markup('<script>unsafe</script>', 'UTF-8')];
        yield 'array' => [['private-value']];
    }

    public function testCustomValueCallbacksCannotReturnRawMarkup(): void
    {
        $view = $this->factory->create(Type\TextType::class, 'original', [
            'summary_value' => static fn (): Markup => new Markup('<script>unsafe</script>', 'UTF-8'),
        ])->createView();
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Cannot summarize a value of type');
        $this->render($view);
    }

    public function testAnExcludedCustomCompoundValueIsNeverEvaluated(): void
    {
        $called = false;
        $view = $this->factory->createBuilder(Type\FormType::class, ['name' => 'private'], [
            'summary' => false,
            'summary_value' => static function () use (&$called): string {
                $called = true;

                return 'private';
            },
        ])->add('name', Type\TextType::class)->getForm()->createView();
        self::assertSame('', $this->render($view, '{{ form_summary(form) }}{{ form_summary_row(form) }}{{ form_summary_value(form) }}'));
        self::assertFalse($called);
    }

    public function testLabelFormatsAndTranslationParametersArePreserved(): void
    {
        $this->translator->addResource('array', ['field.status' => 'Status for %name%', 'ready' => 'Ready for %name%'], 'en', 'booking');
        $view = $this->factory->createNamed('status', Type\ChoiceType::class, 'r', [
            'label_format' => 'field.%name%',
            'label_translation_parameters' => ['%name%' => 'Alkin'],
            'translation_domain' => 'booking',
            'choices' => ['ready' => 'r'],
            'choice_translation_parameters' => ['%name%' => 'Ruse'],
        ])->createView();
        $html = $this->render($view);
        self::assertStringContainsString('Status for Alkin', $html);
        self::assertStringContainsString('Ready for Ruse', $html);
    }

    public function testTranslatableChoiceLabelsKeepTheirDomainAndAreEscaped(): void
    {
        $this->translator->addResource('array', ['ready' => '<b>Ready %name%</b>'], 'en', 'choices');
        $view = $this->factory->create(Type\ChoiceType::class, 'r', [
            'choices' => ['ready' => 'r'],
            'choice_label' => static fn (): TranslatableMessage => new TranslatableMessage('ready', ['%name%' => 'Alkin'], 'choices'),
        ])->createView();
        self::assertStringContainsString('&lt;b&gt;Ready Alkin&lt;/b&gt;', $this->render($view));
    }

    public function testBackedEnumChoicesUseTheirLabels(): void
    {
        $view = $this->factory->create(Type\EnumType::class, SummaryStatus::Ready, ['class' => SummaryStatus::class])->createView();
        self::assertStringContainsString('Ready', $this->render($view));
        self::assertStringNotContainsString('>r<', $this->render($view));
    }

    #[DataProvider('percentSymbols')]
    public function testPercentSymbolsDoNotChangeTheTransformedValue(string|false $symbol, string $expected): void
    {
        $view = $this->factory->create(Type\PercentType::class, 0.15, ['symbol' => $symbol])->createView();
        self::assertSame($expected, trim($this->render($view, '{{ form_summary_value(form) }}')));
    }

    public static function percentSymbols(): iterable
    {
        yield 'default' => ['%', '15%'];
        yield 'disabled' => [false, '15'];
        yield 'custom' => [' per cent', '15 per cent'];
    }

    public function testMoneyRespectsItsDivisorAndDoesNotInventACurrency(): void
    {
        $view = $this->factory->create(Type\MoneyType::class, 1250, ['divisor' => 100, 'currency' => false])->createView();
        self::assertSame('12.50', trim($this->render($view, '{{ form_summary_value(form) }}')));
    }

    public function testDisabledAndUnmappedValuesRemainVisible(): void
    {
        $view = $this->factory->createBuilder(Type\FormType::class, ['name' => 'Existing'])
            ->add('name', Type\TextType::class, ['disabled' => true])
            ->add('info', Type\TextType::class, ['mapped' => false, 'data' => 'Extra information'])
            ->getForm()->createView();
        $html = $this->render($view);
        self::assertStringContainsString('Existing', $html);
        self::assertStringContainsString('Extra information', $html);
    }

    public function testSummariesStillWorkAfterTheEditableFormIsRendered(): void
    {
        $view = $this->factory->createNamed('note', Type\TextType::class, 'Visible')->createView();
        $this->render($view, '{{ form_widget(form) }}');
        self::assertTrue($view->isRendered());
        self::assertStringContainsString('<dd>Visible</dd>', $this->render($view));
    }

    public function testAnOnlyThemeCanExplicitlyIncludeTheDefaultSummaryTheme(): void
    {
        $this->templates->setTemplate('custom.html.twig', '{% block text_summary_value %}<strong>{{ value }}</strong>{% endblock %}');
        $view = $this->factory->create(Type\TextType::class, '<Custom>')->createView();
        $html = $this->render($view, '{% form_theme form with ["@FormSummary/form_summary_layout.html.twig", "custom.html.twig"] only %}{{ form_summary(form) }}');
        self::assertStringContainsString('<strong>&lt;Custom&gt;</strong>', $html);
    }

    public function testFieldSpecificThemesAreIsolatedBetweenFormViews(): void
    {
        $this->templates->setTemplate('custom.html.twig', '{% block _first_note_summary_value %}<strong>{{ value }}</strong>{% endblock %}');
        $first = $this->factory->createNamedBuilder('first', Type\FormType::class, ['note' => 'First'])->add('note', Type\TextType::class)->getForm()->createView();
        $second = $this->factory->createNamedBuilder('second', Type\FormType::class, ['note' => 'Second'])->add('note', Type\TextType::class)->getForm()->createView();
        $this->twig->getRuntime(FormRenderer::class)->setTheme($first, ['custom.html.twig']);
        self::assertStringContainsString('<strong>First</strong>', $this->render($first));
        self::assertStringNotContainsString('<strong>', $this->render($second));
        self::assertStringContainsString('<dd>Second</dd>', $this->render($second));
    }

    public function testABootstrapFormThemeRemainsAvailableAfterSummarizing(): void
    {
        $view = $this->factory->createNamed('note', Type\TextType::class, 'Visible')->createView();
        $this->twig->getRuntime(FormRenderer::class)->setTheme($view, ['bootstrap_5_layout.html.twig']);
        self::assertStringContainsString('<dd>Visible</dd>', $this->render($view));
        self::assertStringContainsString('form-control', $this->render($view, '{{ form_widget(form) }}'));
    }
}

enum SummaryStatus: string
{
    case Ready = 'r';
    case Pending = 'p';
}
