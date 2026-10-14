# Changelog

## [1.0.0] - 2026-10-14

Initial stable release.

- Render complete forms, individual rows or values with `form_summary()`, `form_summary_row()` and `form_summary_value()`.
- Reuse existing field labels, translation settings, selected choice labels and transformed view values.
- Support nested forms, collections, enums, money, percentages, dates and times without introducing a second field schema.
- Preserve zero, false and empty values as distinct cases. Propagate custom empty placeholders into partial date and time widgets.
- Exclude sensitive field types, buttons and explicitly excluded fields, including their descendants and excluded expanded-choice children.
- Support lazy, escaped custom textual values, including replacements for compound forms.
- Integrate with native form themes without replacing editable widget blocks or marking fields as rendered.
- Include English and Bulgarian boolean translations, an MIT license, regression tests and production-archive installation checks.
