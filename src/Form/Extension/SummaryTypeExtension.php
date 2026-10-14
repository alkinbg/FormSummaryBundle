<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatableInterface;

final class SummaryTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class, ButtonType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('summary', true);
        $resolver->setAllowedTypes('summary', 'bool');
        $resolver->setDefined('summary_value');
        $resolver->setAllowedTypes('summary_value', ['null', 'string', 'int', 'float', 'bool', \Closure::class, TranslatableInterface::class]);
    }

    /** @param array<string, mixed> $options */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['summary'] = $options['summary'];
        $view->vars['summary_has_custom_value'] = \array_key_exists('summary_value', $options);
        $view->vars['summary_custom_value'] = $options['summary_value'] ?? null;
    }
}
