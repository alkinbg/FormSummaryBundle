<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle;

use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Twig\Environment;

final readonly class SummaryRenderer
{
    public function __construct(private ValueFormatter $formatter)
    {
    }

    /** @param array<string, mixed> $variables */
    public function render(Environment $environment, FormView $view, array $variables = []): string
    {
        return $this->renderBlock($environment, $view, 'summary', $variables);
    }

    /** @param array<string, mixed> $variables */
    public function renderRow(Environment $environment, FormView $view, array $variables = []): string
    {
        return $this->renderBlock($environment, $view, 'summary_row', $variables);
    }

    /** @param array<string, mixed> $variables */
    public function renderValue(Environment $environment, FormView $view, array $variables = []): string
    {
        if (!Visibility::isVisible($view)) {
            return '';
        }

        if ($view->vars['summary_has_custom_value'] ?? false) {
            $value = $view->vars['summary_custom_value'];
            $variables['summary_display_value'] = $this->formatter->normalize($value instanceof \Closure ? $value($view) : $value);

            return $this->renderBlock($environment, $view, 'summary_custom_value', $variables);
        }

        return $this->renderBlock($environment, $view, 'summary_value', $variables);
    }

    /** @param array<string, mixed> $variables */
    private function renderBlock(Environment $environment, FormView $view, string $suffix, array $variables): string
    {
        if (!Visibility::isVisible($view)) {
            return '';
        }

        $variables['summary_formatter'] = $this->formatter;
        $variables += ['summary_empty' => '—'];

        return $environment->getRuntime(FormRenderer::class)->searchAndRenderBlock($view, $suffix, $variables);
    }
}
