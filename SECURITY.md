# Security Policy

## Supported versions

Security fixes are considered for the current `1.x` release line. Older release lines and unreleased development branches are not guaranteed to receive security updates.

| Version | Security updates |
| --- | --- |
| Latest `1.x` | Supported |
| Unreleased branches | Not guaranteed |

## Reporting a vulnerability

**Do not disclose suspected vulnerabilities through public GitHub issues or pull requests.**

Send a private report to **alkinbg@gmail.com** with the subject `FormSummaryBundle security report`. Include affected versions, reproduction steps, potential impact, and a minimal proof of concept if available. Do not include unrelated personal or production data.

Reports will be reviewed and, when appropriate, addressed through a patch and coordinated disclosure. No fixed response or release timeframe is promised.

## Security boundary

FormSummaryBundle renders read-only output from Symfony `FormView` values. It does **not** authorize access, validate submissions, or decide which business data a user is permitted to see. Applications must handle those responsibilities and explicitly exclude any sensitive fields they do not want displayed. Refer to the [README](README.md#scope-and-safety) for the complete scope and limitations.
