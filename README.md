# FormSummaryBundle

**Define the form once. Display its data without writing the same fields again.**

```twig
{{ form_summary(form) }}
```

FormSummaryBundle turns a Symfony `FormView` into a read-only description list. It reuses the form's labels, selected choice labels, translation settings and transformed view values. No disabled inputs, JavaScript, database tables or CSS framework required.

```html
<dl class="form-summary">
    <div class="form-summary__row"><dt>Carrier</dt><dd>Example Logistics</dd></div>
    <div class="form-summary__row"><dt>Status</dt><dd>Ready to load</dd></div>
    <div class="form-summary__row"><dt>Pallets</dt><dd>24</dd></div>
</dl>
```

## Requirements

PHP 8.2 or later with a compatible Symfony version: Symfony 6.4, 7.4 or 8.x and Twig 3.15 or later. Symfony's own PHP requirements still apply. Install the current security patches of your chosen dependency versions.

CI covers PHP 8.2–8.5 with Symfony 6.4, 7.4, 8.0 and 8.1, plus a lowest-dependency job and production-archive installation checks. The lowest-dependency job respects Composer's security advisory blocking; it does not deliberately install vulnerable releases.

## Installation

FormSummaryBundle is available on [Packagist](https://packagist.org/packages/alkin/form-summary-bundle). Install the stable `1.x` series with Composer:

```bash
composer require "alkin/form-summary-bundle:^1.0"
```

No custom Composer repository configuration is needed when Packagist is enabled.

Register the bundle in `config/bundles.php`:

```php
Alkin\FormSummaryBundle\FormSummaryBundle::class => ['all' => true],
```

Keep the application's existing `FrameworkBundle` and `TwigBundle` registrations and enable Symfony Forms as usual. The bundle registers its form extension, Twig functions and default summary theme automatically. No Flex recipe is required or assumed.

## Basic usage

Use an existing form; a second form definition or summary schema is unnecessary.

```php
// In a controller extending Symfony's AbstractController:
$form = $this->createForm(BookingType::class, $booking);

return $this->render('booking/show.html.twig', [
    'form' => $form->createView(),
]);
```

```twig
{# templates/booking/show.html.twig #}
{{ form_summary(form) }}
```

For a confirmation after submission, handle the request and check `$form->isSubmitted()` and `$form->isValid()` in the application before treating the data as valid. Rendering a summary neither submits nor validates a form.

### Individual fields

```twig
<dl class="form-summary">
    {{ form_summary_row(form.carrier) }}
    {{ form_summary_row(form.status) }}
</dl>

<p>Selected status: {{ form_summary_value(form.status) }}</p>
```

All three functions accept a `FormView`, including a child view. `form_summary()` includes the outer `<dl>`, `form_summary_row()` produces a `<div>` containing `<dt>` and `<dd>`, and `form_summary_value()` renders only the value.

Summaries do not mark fields as rendered. The same view can be summarized repeatedly, before or after displaying the editable form. `form_widget()` and `form_rest()` remain usable afterwards.

### Empty values

`null` and the empty string use an em dash. Zero remains `0`; an unchecked checkbox is `No`, not an empty value.

```twig
{{ form_summary(form, {summary_empty: 'Not provided'}) }}
```

`summary_empty` must be a string. It is propagated to nested summaries and missing components of partial dates and times, and is escaped as text. An entirely empty date or time displays one placeholder rather than a placeholder for each component. An empty-string placeholder is supported.

## Excluding fields

```php
$builder->add('internalNote', TextareaType::class, [
    'summary' => false,
]);
```

Excluding a compound parent excludes its descendants, including attempts to render a child directly through a summary function. Excluded child views of expanded choices are also omitted when summarizing the parent choice field.

Password, hidden, button, submit, reset, file and repeated fields are always excluded, including types inheriting from them. Setting `summary: true` or `summary_value` cannot enable them. CSRF tokens are hidden fields and are not included. Repeated fields are deliberately excluded rather than accidentally repeating confirmation values.

Other fields, including disabled and unmapped fields, are included unless explicitly excluded. A secret stored in an ordinary `TextType` is not automatically recognizable: set `summary: false` for it. This is presentation filtering, not an authorization system.

## Custom values

Use `summary_value` for a custom field or a different textual representation. It accepts a scalar, `null`, a `TranslatableInterface` implementation or a closure receiving the field's `FormView`.

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

$builder->add('reference', TextType::class, [
    'summary_value' => static fn (FormView $view): string =>
        'REF-'.strtoupper((string) $view->vars['value']),
]);
```

The closure is evaluated only when that value is summarized, not when the form view is created. It is never evaluated for an excluded field. Rendering again evaluates it again; keep callbacks free of side effects.

Explicit `null` replaces the original value with the empty placeholder. It does not fall back to the original data. A custom value on a compound form replaces its child list through all three summary functions.

Values and callback results are escaped. Arbitrary objects, arrays and raw Twig `Markup` are rejected instead of being serialized or stringified. Return a supported value or provide a custom Twig summary block for richer output.

## Supported fields

| Field family | Summary |
| --- | --- |
| Text, textarea, email, URL, integer, number, and scalar custom fields | Transformed view value, escaped as text |
| Choice, enum, country, locale, language and other `ChoiceType` descendants | Selected labels, including grouped, preferred, expanded and multiple choices |
| Checkbox and radio | Translated `Yes` / `No` |
| Money | Transformed amount in the field's currency pattern |
| Percent | Transformed amount and configured symbol |
| Date, time and datetime | Existing view representation; compound date pattern and time parts are supported |
| Nested forms and collections | Nested description lists, existing children only; no collection prototype |

The form remains responsible for choices, transformers and time zones. A single-text HTML5 date or datetime retains its configured view format; the bundle does not silently choose a different locale format. Native form creation and choice loading may still perform their usual database queries. Summary rendering uses the existing choice views and does not reload the choice loader, query entities or serialize model objects itself.

Compound dates and times assemble their standard component view values. Custom values or theme blocks attached to internal components, such as `date.year`, are not composed into the parent's formatted date. Customize the parent date/time field with `summary_value` or its summary theme block instead. Non-scalar date/time component labels require a custom parent representation.

For unsupported compound widgets, supply `summary_value` or a field-specific summary theme block rather than relying on an inferred representation. A field configured with `label: false` retains an empty label in the default summary; provide a suitable label or a custom row theme when a visible heading is needed.

## Translations

Field labels use Symfony's `field_label()`, including `label_format`, label parameters and translation domains. Choice labels reuse `choice_translation_domain` and each choice's translation parameters. A disabled translation domain is respected.

Boolean labels use the `form_summary` translation domain. English and Bulgarian messages are included. For another language, add an application catalogue, for example `translations/form_summary.fr.yaml`:

```yaml
Yes: Oui
No: Non
```

`TranslatableInterface` values, including `TranslatableMessage`, are translated before escaping. When the application's translator is disabled, the default English boolean labels remain usable.

## Custom themes

Summary blocks participate in Symfony's native form-theme lookup. Override a field type, a specific field or the default summary row using the same approach as ordinary form themes.

```twig
{# templates/form/summary.html.twig #}
{% block text_summary_value %}
    <span class="my-summary-text">{{ value }}</span>
{% endblock %}
```

```twig
{% form_theme form 'form/summary.html.twig' %}
{{ form_summary(form) }}
```

The default summary theme is registered globally and contains only summary blocks. It does not import or replace the application's editable widget blocks. An override file only needs its changed blocks.

A `{% form_theme ... only %}` theme disables global fallback. Include the default summary theme explicitly; also include an editable form theme when the same view will be rendered for editing:

```twig
{% form_theme form with [
    'bootstrap_5_layout.html.twig',
    '@FormSummary/form_summary_layout.html.twig',
    'form/summary.html.twig'
] only %}
```

Available suffixes include `summary`, `summary_row` and `summary_value`, for example `choice_summary_value`, `date_summary_value`, or `_booking_reference_summary_value`. A supplied `summary_value` uses `summary_custom_value` instead, so it is not formatted a second time by the original field type. Override `form_summary_custom_value` or a field-specific equivalent to customize that output.

Default theme classes are `form-summary`, `form-summary__row` and `form-summary__choices`. No stylesheet is injected. Style these classes in the application or override the blocks for a different layout.

The default theme escapes labels even when the form's `label_html` option is enabled, and explicitly enables HTML escaping even when application autoescaping is disabled. Custom themes are trusted application code; keep escaping enabled and do not apply `raw` to user-provided data.

## Scope and safety

The bundle only displays a form view. It does not persist data, authorize access, manage multi-step workflows, send mail or generate PDFs. It also does not make submitted data trustworthy: the application owns validation, authorization and any confirmation or save operation.

Creating a Symfony form view may generate its normal CSRF token. The summary never emits the token. For an application-owned display-only form, the application may explicitly configure CSRF behavior as appropriate; the bundle does not alter it.

The three Twig functions, documented form options and summary theme blocks are the integration API. Classes marked `@internal` are implementation details and are not extension points.

## Development

From a source checkout:

```bash
composer install
composer validate --strict
composer qa
composer audit --locked
```

The development suite requires PHP's DOM, XML, mbstring and intl extensions. `composer qa` runs PHPUnit, PHPStan level 8 and portable PHP syntax checks. Tests fail on warnings, notices, risky tests and deprecations. CI additionally tests randomized execution order, lowest secure dependency versions, and the exported distribution installed in a production-mode Symfony kernel without development dependencies.

Distribution archives omit tests, tools and CI configuration, but include runtime code, templates, translations, the README, changelog and license.

### Releases

Update the changelog and merge a release commit whose message is exactly `Release X.Y.Z` into `main`. After the main-branch test and installation jobs pass, CI creates the annotated `vX.Y.Z` tag using the package author's name and email. An existing tag is never moved to a different commit. Tagging does not publish the package to Packagist.

## License

MIT. Copyright (c) 2026 Alkin Fehim.
