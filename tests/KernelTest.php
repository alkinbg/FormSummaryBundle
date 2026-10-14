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
use Symfony\Component\HttpKernel\Kernel;

final class KernelTest extends TestCase
{
    public function testBundleWiresServicesThemesAndTranslationsInARealKernel(): void
    {
        self::assertTrue(class_exists(FormSummaryBundle::class), 'The Symfony bundle must be available.');
        $kernel = new SummaryKernel('test', true);
        try {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            $form = $container->get('form.factory')->createBuilder(FormType::class, ['name' => 'Alkin', 'confirmed' => true])->add('name', TextType::class)->add('confirmed', CheckboxType::class)->getForm();
            $view = $form->createView();
            $twig = $container->get('twig');
            $html = $twig->createTemplate('{{ form_summary(form) }}')->render(['form' => $view]);
            self::assertStringContainsString('Alkin', $html);
            self::assertStringContainsString('Да', $html);
            self::assertStringNotContainsString('_token', $html);
            self::assertStringContainsString('<input', $twig->createTemplate('{{ form_rest(form) }}')->render(['form' => $view]));
        } finally {
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
            $container->loadFromExtension('framework', ['secret' => 'form-summary-tests', 'test' => true, 'form' => true, 'csrf_protection' => true, 'default_locale' => 'bg', 'translator' => ['fallbacks' => ['en']]]);
            $container->loadFromExtension('twig', ['strict_variables' => true]);
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
