<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Tests;

use Alkin\FormSummaryBundle\FormSummaryBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Kernel;

final class KernelTest extends TestCase
{
    public function testBundleWiresServicesThemesAndTranslationsInARealKernel(): void
    {
        $kernel = new SummaryKernel('test', true);
        $requestStack = null;

        try {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            $request = Request::create('/');
            $request->setSession(new Session(new MockArraySessionStorage()));
            $requestStack = $container->get('request_stack');
            $requestStack->push($request);

            $form = $container->get('test.form_factory')
                ->createBuilder(FormType::class, ['name' => 'Alkin', 'confirmed' => true])
                ->add('name', TextType::class)
                ->add('confirmed', CheckboxType::class)
                ->getForm();
            $view = $form->createView();
            self::assertTrue(isset($view['_token']), 'The test must exercise a real CSRF field.');

            $twig = $container->get('test.twig');
            $html = $twig->createTemplate('{{ form_summary(form) }}')->render(['form' => $view]);
            self::assertStringContainsString('Alkin', $html);
            self::assertStringContainsString('Да', $html);
            self::assertStringNotContainsString('_token', $html);
            self::assertStringNotContainsString($view['_token']->vars['value'], $html);

            $editable = $twig->createTemplate('{{ form_rest(form) }}')->render(['form' => $view]);
            self::assertStringContainsString('<input', $editable);
            self::assertStringContainsString('_token', $editable);
        } finally {
            $requestStack?->pop();
            $kernel->shutdown();
        }
    }
}

final class SummaryKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new FormSummaryBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'form-summary-tests',
                'test' => true,
                'form' => true,
                'csrf_protection' => true,
                'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
                'default_locale' => 'bg',
                'translator' => ['fallbacks' => ['en']],
            ]);
            $container->loadFromExtension('twig', ['strict_variables' => true]);

            // Keep services used directly by this controller-free test kernel.
            $container->setAlias('test.form_factory', 'form.factory')->setPublic(true);
            $container->setAlias('test.twig', 'twig')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/form-summary-tests/'.getmypid().'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/form-summary-tests/'.getmypid().'/log';
    }
}
