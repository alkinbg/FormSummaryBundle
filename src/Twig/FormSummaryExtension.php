<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle\Twig;

use Alkin\FormSummaryBundle\SummaryRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormSummaryExtension extends AbstractExtension
{
    public function __construct(private readonly SummaryRenderer $renderer)
    {
    }

    public function getFunctions(): array
    {
        $options = ['needs_environment' => true, 'is_safe' => ['html']];

        return [
            new TwigFunction('form_summary', [$this->renderer, 'render'], $options),
            new TwigFunction('form_summary_row', [$this->renderer, 'renderRow'], $options),
            new TwigFunction('form_summary_value', [$this->renderer, 'renderValue'], $options),
        ];
    }
}
