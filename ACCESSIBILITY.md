# Accessibility

FormSummaryBundle provides a read-only representation of Symfony forms using semantic HTML description lists (`<dl>`, `<dt>`, and `<dd>`). It does not inject JavaScript, CSS, or interactive controls into the summary.

Accessible output still depends on the application's labels, localization, form structure, styling, and any custom form-summary theme overrides. Using this bundle does not, by itself, establish conformance with WCAG or another accessibility standard.

When customizing summary themes, preserve meaningful labels, readable text, adequate contrast, and keyboard and screen-reader usability wherever interactive elements are introduced by the application.

Please report accessibility bugs or barriers through the repository's [issue tracker](https://github.com/alkinbg/FormSummaryBundle/issues). Include the affected screen-reader/browser combination where relevant and a reproducible example without private data. For security-sensitive findings, use the [security policy](SECURITY.md) instead.
