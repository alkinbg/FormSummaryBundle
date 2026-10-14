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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class RenderingTest extends TestCase
{
    private Environment $twig;
    private Translator $translator;
    private TwigArrayLoader $templates;
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
        $this->twig = new Environment(new ChainLoader([$this->templates, $loader]), ['strict_variables' => true, 'autoescape' => 'html']);
        $this->twig->addExtension(new FormExtension($this->translator));
        $this->twig->addExtension(new TranslationExtension($this->translator));
        $this->twig->addExtension(new FormSummaryExtension(new SummaryRenderer(new ValueFormatter())));
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn (): FormRenderer => new FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig', '@FormSummary/form_summary_layout.html.twig'], $this->twig)),
        ]));
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    private function builder(array $data = []): \Symfony\Component\Form\FormBuilderInterface
    {
        return Forms::createFormFactoryBuilder()->addTypeExtension(new SummaryTypeExtension())->getFormFactory()->createBuilder(Type\FormType::class, $data);
    }

    private function render(FormInterface|FormView $form, string $template = '{{ form_summary(form) }}'): string
    {
        return $this->twig->createTemplate($template)->render(['form' => $form instanceof FormInterface ? $form->createView() : $form]);
    }

    public function testItRendersLabelsAndValuesWithoutInputs(): void
    {
        $form = $this->builder(['company' => 'Example Logistics'])->add('company', Type\TextType::class, ['label' => 'Carrier'])->getForm();
        $html = $this->render($form);
        self::assertStringContainsString('<dl', $html);
        self::assertStringContainsString('<dt>Carrier</dt>', $html);
        self::assertStringContainsString('Example Logistics', $html);
        self::assertDoesNotMatchRegularExpression('/<(?:input|select|textarea|button|form)\b/i', $html);
    }

    public function testZeroFalseAndMissingDataRemainDistinct(): void
    {
        $form = $this->builder(['pallets' => 0, 'confirmed' => false])->add('pallets', Type\IntegerType::class)->add('confirmed', Type\CheckboxType::class)->add('note', Type\TextType::class)->getForm();
        $view = $form->createView();
        self::assertSame('0', trim($this->render($view['pallets'], '{{ form_summary_value(form) }}')));
        self::assertSame('No', trim($this->render($view['confirmed'], '{{ form_summary_value(form) }}')));
        self::assertSame('—', trim($this->render($view['note'], '{{ form_summary_value(form) }}')));
    }

    #[DataProvider('choiceModes')]
    public function testChoicesShowLabelsRatherThanIdentifiers(bool $expanded, bool $multiple): void
    {
        $form = $this->builder(['status' => $multiple ? ['ready', 'new'] : 'ready'])->add('status', Type\ChoiceType::class, [
            'choices' => ['First' => ['New booking' => 'new'], 'Second' => ['Ready to load' => 'ready']],
            'preferred_choices' => ['ready'], 'expanded' => $expanded, 'multiple' => $multiple,
        ])->getForm();
        $html = $this->render($form);
        self::assertSame(1, substr_count($html, 'Ready to load'));
        self::assertSame($multiple ? 1 : 0, substr_count($html, 'New booking'));
        self::assertStringNotContainsString('>ready<', $html);
        self::assertStringNotContainsString('>Yes<', $html);
    }

    public static function choiceModes(): iterable
    {
        yield 'select' => [false, false];
        yield 'radios' => [true, false];
        yield 'multiple select' => [false, true];
        yield 'checkboxes' => [true, true];
    }

    public function testAnIntegerZeroChoiceIsSelected(): void
    {
        $form = $this->builder(['level' => 0])->add('level', Type\ChoiceType::class, ['choices' => ['None' => 0, 'One' => 1]])->getForm();
        self::assertStringContainsString('None', $this->render($form));
    }

    public function testSensitiveFieldsAndButtonsCannotBeEnabled(): void
    {
        $called = false;
        $form = $this->builder(['password' => 'secret-password', 'token' => 'secret-token'])
            ->add('password', Type\PasswordType::class, ['always_empty' => false, 'summary' => true, 'summary_value' => static function () use (&$called): string { $called = true; return 'callback-secret'; }])
            ->add('token', Type\HiddenType::class, ['summary' => true])
            ->add('upload', Type\FileType::class, ['mapped' => false])
            ->add('save', Type\SubmitType::class)
            ->getForm();
        $html = $this->render($form);
        foreach (['secret-password', 'secret-token', 'callback-secret', 'Password', 'Upload', 'Save'] as $secret) {
            self::assertStringNotContainsString($secret, $html);
        }
        self::assertFalse($called);
        foreach ($form->createView() as $child) {
            self::assertSame('', $this->render($child, '{{ form_summary_row(form) }}{{ form_summary_value(form) }}'));
        }
    }

    public function testExcludedParentsAlsoProtectDirectChildRendering(): void
    {
        $builder = $this->builder(['private' => ['note' => 'confidential']]);
        $builder->add($builder->create('private', Type\FormType::class, ['summary' => false])->add('note', Type\TextType::class));
        $view = $builder->getForm()->createView();
        self::assertStringNotContainsString('confidential', $this->render($view));
        self::assertSame('', $this->render($view['private']['note'], '{{ form_summary_value(form) }}'));
    }

    public function testTextAndLabelsAreEscapedEvenWithLabelHtmlEnabled(): void
    {
        $form = $this->builder(['note' => '<script>alert(1)</script>'])->add('note', Type\TextType::class, ['label' => '<img src=x onerror=alert(1)>', 'label_html' => true])->getForm();
        $html = $this->render($form);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    public function testChoiceLabelsAreEscaped(): void
    {
        $form = $this->builder(['status' => 'ready'])->add('status', Type\ChoiceType::class, ['choices' => ['<b>Ready</b>' => 'ready'], 'choice_label' => static fn (): string => '<b>Ready</b>'])->getForm();
        self::assertStringContainsString('&lt;b&gt;Ready&lt;/b&gt;', $this->render($form));
    }

    public function testCustomValuesAreLazyAndEscaped(): void
    {
        $calls = 0;
        $form = $this->builder(['reference' => 'raw'])->add('reference', Type\TextType::class, ['summary_value' => static function (FormView $view) use (&$calls): string { ++$calls; return '<b>'.$view->vars['value'].'</b>'; }])->getForm();
        $view = $form->createView();
        self::assertSame(0, $calls);
        self::assertStringContainsString('&lt;b&gt;raw&lt;/b&gt;', $this->render($view));
        self::assertSame(1, $calls);
    }

    public function testCustomNullIsNotReplacedWithOriginalData(): void
    {
        $form = $this->builder(['note' => 'original'])->add('note', Type\TextType::class, ['summary_value' => null])->getForm();
        $html = $this->render($form);
        self::assertStringNotContainsString('original', $html);
        self::assertStringContainsString('—', $html);
    }

    public function testSummaryCanBeRepeatedAndDoesNotConsumeFormFields(): void
    {
        $view = $this->builder(['name' => 'Alkin'])->add('name', Type\TextType::class)->getForm()->createView();
        $first = $this->render($view);
        self::assertSame($first, $this->render($view));
        self::assertFalse($view->isRendered());
        self::assertFalse($view['name']->isRendered());
        self::assertStringContainsString('<input', $this->render($view, '{{ form_rest(form) }}'));
    }

    public function testNestedFormsAndCollectionsUseExistingChildrenOnly(): void
    {
        $builder = $this->builder(['address' => ['city' => 'Ruse'], 'names' => ['Alice', 'Bob']]);
        $builder->add($builder->create('address', Type\FormType::class)->add('city', Type\TextType::class));
        $builder->add('names', Type\CollectionType::class, ['entry_type' => Type\TextType::class, 'allow_add' => true]);
        $html = $this->render($builder->getForm());
        foreach (['Ruse', 'Alice', 'Bob'] as $value) { self::assertStringContainsString($value, $html); }
        self::assertStringNotContainsString('__name__', $html);
        self::assertGreaterThanOrEqual(3, substr_count($html, '<dl'));
    }

    public function testNativeLabelAndChoiceTranslationSettingsAreReused(): void
    {
        $this->translator->addResource('array', ['field.status' => 'Delivery status', 'ready' => 'Ready now'], 'en', 'booking');
        $form = $this->builder(['status' => 'r'])->add('status', Type\ChoiceType::class, ['label' => 'field.status', 'translation_domain' => 'booking', 'choices' => ['ready' => 'r']])->getForm();
        $html = $this->render($form);
        self::assertStringContainsString('Delivery status', $html);
        self::assertStringContainsString('Ready now', $html);
    }

    public function testDisabledTranslationAndTranslatableMessagesAreSupported(): void
    {
        $this->translator->addResource('array', ['raw' => 'SHOULD NOT APPEAR', 'hello' => 'Hello %name%'], 'en');
        $form = $this->builder(['raw' => 'r', 'greeting' => 'unused'])
            ->add('raw', Type\ChoiceType::class, ['label' => 'raw', 'translation_domain' => false, 'choice_translation_domain' => false, 'choices' => ['raw' => 'r']])
            ->add('greeting', Type\TextType::class, ['summary_value' => new TranslatableMessage('hello', ['%name%' => 'Alkin'])])->getForm();
        $html = $this->render($form);
        self::assertStringNotContainsString('SHOULD NOT APPEAR', $html);
        self::assertStringContainsString('Hello Alkin', $html);
    }

    public function testMoneyAndPercentReuseTransformedViewValues(): void
    {
        $form = $this->builder(['price' => 12.5, 'ratio' => 0.15])->add('price', Type\MoneyType::class, ['currency' => 'EUR'])->add('ratio', Type\PercentType::class)->getForm();
        $html = $this->render($form);
        self::assertStringContainsString('12.50', $html);
        self::assertStringContainsString('€', $html);
        self::assertStringContainsString('15%', $html);
    }

    public function testCompoundDatesRespectTheirConfiguredPattern(): void
    {
        $form = $this->builder(['date' => new \DateTimeImmutable('2026-10-14')])->add('date', Type\DateType::class, ['input' => 'datetime_immutable', 'widget' => 'choice', 'format' => 'dd.MM.yyyy'])->getForm();
        self::assertStringContainsString('14.10.2026', $this->render($form));
    }

    public function testCompoundTimeRetainsMinutesAndSeconds(): void
    {
        $form = $this->builder(['time' => new \DateTimeImmutable('2026-10-14 09:05:07')])->add('time', Type\TimeType::class, ['input' => 'datetime_immutable', 'widget' => 'choice', 'with_seconds' => true])->getForm();
        self::assertStringContainsString('09:05:07', $this->render($form));
    }

    public function testDatetimeUsesTheViewTimezoneRatherThanModelTimezone(): void
    {
        $form = $this->builder(['at' => new \DateTimeImmutable('2026-10-14 07:00:00', new \DateTimeZone('UTC'))])->add('at', Type\DateTimeType::class, ['input' => 'datetime_immutable', 'widget' => 'single_text', 'model_timezone' => 'UTC', 'view_timezone' => 'Europe/Sofia'])->getForm();
        self::assertStringContainsString('10:00', $this->render($form));
    }

    public function testCustomSummaryBlocksUseTheNativeFormThemeMechanism(): void
    {
        $this->templates->setTemplate('custom.html.twig', '{% block text_summary_value %}<strong>{{ value }}</strong>{% endblock %}');
        $view = $this->builder(['note' => 'Custom'])->add('note', Type\TextType::class)->getForm()->createView();
        $this->twig->getRuntime(FormRenderer::class)->setTheme($view, ['custom.html.twig']);
        self::assertStringContainsString('<strong>Custom</strong>', $this->render($view));
    }

    public function testInvalidSubmittedTextIsEscapedWithoutPretendingToValidateIt(): void
    {
        $form = $this->builder()->add('amount', Type\IntegerType::class)->getForm();
        $form->submit(['amount' => '<script>bad</script>']);
        self::assertFalse($form->get('amount')->isSynchronized());
        self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $this->render($form));
    }
}
