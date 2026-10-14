<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle;

use Alkin\FormSummaryBundle\Form\Extension\SummaryTypeExtension;
use Alkin\FormSummaryBundle\Twig\FormSummaryExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class FormSummaryBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig', [
            'form_themes' => ['@FormSummary/form_summary_layout.html.twig'],
        ]);
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(SummaryTypeExtension::class)->tag('form.type_extension');
        $services->set(ValueFormatter::class);
        $services->set(SummaryRenderer::class)->args([service(ValueFormatter::class)]);
        $services->set(FormSummaryExtension::class)
            ->args([service(SummaryRenderer::class)])
            ->tag('twig.extension');
    }
}
