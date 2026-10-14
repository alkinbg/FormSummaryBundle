<?php

declare(strict_types=1);

namespace Alkin\FormSummaryBundle;

use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatableInterface;

/** @internal Values are formatted from FormView, never by serializing model data. */
final class ValueFormatter
{
    public function normalize(mixed $value): string|int|float|bool|TranslatableInterface|null
    {
        if (null === $value || \is_scalar($value) || $value instanceof TranslatableInterface) {
            return $value;
        }

        throw new \UnexpectedValueException(\sprintf(
            'Cannot summarize a value of type "%s". Provide a scalar summary_value or a custom summary_value form theme block.',
            get_debug_type($value),
        ));
    }

    public function text(FormView $view): string|int|float|bool|TranslatableInterface|null
    {
        return $this->normalize($view->vars['value'] ?? null);
    }

    public function isTranslatable(mixed $value): bool
    {
        return $value instanceof TranslatableInterface;
    }

    /** @return list<ChoiceView> */
    public function choices(FormView $view): array
    {
        $value = $view->vars['value'] ?? null;
        $selected = \is_array($value) ? $value : (null === $value ? [] : [$value]);
        $result = [];
        $seen = [];

        foreach ([$view->vars['preferred_choices'] ?? [], $view->vars['choices'] ?? []] as $choices) {
            foreach ($this->flatten($choices) as $choice) {
                $key = 'value:'.$choice->value;
                if (isset($seen[$key]) || !\in_array($choice->value, $selected, true)) {
                    continue;
                }

                $seen[$key] = true;
                $result[] = $choice;
            }
        }

        return $result;
    }

    public function money(FormView $view): ?string
    {
        $value = $this->plain($view);

        return null === $value ? null : str_replace('{{ widget }}', $value, $view->vars['money_pattern'] ?? '{{ widget }}');
    }

    public function percent(FormView $view): ?string
    {
        $value = $this->plain($view);

        return null === $value ? null : $value.($view->vars['symbol'] ?? '%');
    }

    public function date(FormView $view): ?string
    {
        if (!($view->vars['compound'] ?? false)) {
            return $this->plain($view);
        }

        $parts = [];
        $hasValue = false;
        foreach (['year', 'month', 'day'] as $name) {
            $value = isset($view->children[$name]) ? $this->plain($view->children[$name]) : null;
            $hasValue = $hasValue || null !== $value;
            $parts['{{ '.$name.' }}'] = $value ?? '—';
        }

        return $hasValue ? strtr($view->vars['date_pattern'] ?? '{{ year }}-{{ month }}-{{ day }}', $parts) : null;
    }

    public function time(FormView $view): ?string
    {
        if (!($view->vars['compound'] ?? false)) {
            return $this->plain($view);
        }

        $parts = [];
        $hasValue = false;
        foreach (['hour', 'minute', 'second'] as $name) {
            if (!isset($view->children[$name])) {
                continue;
            }

            $value = $this->plain($view->children[$name]);
            $hasValue = $hasValue || null !== $value;
            $parts[] = null === $value ? '—' : str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return $hasValue ? implode(':', $parts) : null;
    }

    public function dateTime(FormView $view): ?string
    {
        if (!($view->vars['compound'] ?? false)) {
            return $this->plain($view);
        }

        $date = isset($view->children['date']) && Visibility::isVisible($view->children['date']) ? $this->date($view->children['date']) : null;
        $time = isset($view->children['time']) && Visibility::isVisible($view->children['time']) ? $this->time($view->children['time']) : null;

        if (null === $date && null === $time) {
            return null;
        }

        return ($date ?? '—').' '.($time ?? '—');
    }

    /**
     * @param iterable<ChoiceView|ChoiceGroupView> $choices
     * @return \Generator<int, ChoiceView>
     */
    private function flatten(iterable $choices): \Generator
    {
        foreach ($choices as $choice) {
            if ($choice instanceof ChoiceGroupView) {
                yield from $this->flatten($choice->choices);
            } else {
                yield $choice;
            }
        }
    }

    private function plain(FormView $view): ?string
    {
        if (!Visibility::isVisible($view)) {
            return null;
        }

        $value = \in_array('choice', $view->vars['block_prefixes'] ?? [], true)
            ? ($this->choices($view)[0]->label ?? null)
            : $this->text($view);

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_scalar($value)) {
            throw new \UnexpectedValueException('A date, time or number part must have a scalar view value. Provide a custom summary_value.');
        }

        return (string) $value;
    }
}
