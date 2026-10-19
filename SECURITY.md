# Security Policy

## Supported Versions

Security fixes are provided for the currently supported release of Dynamic Location Menus.

| Version | Supported |
| ------- | --------- |
| 1.0.x   | Yes       |

## Reporting a Vulnerability

If you discover a security issue in Dynamic Location Menus, please report it responsibly.

**Do not** open a public GitHub issue for security vulnerabilities.

Instead, contact Duppins Technology directly:

- Website: https://duppinstechnology.com/
- Include a clear description of the issue, affected version, and steps to reproduce if possible

We aim to acknowledge reports within 5 business days and will work with you on validation and remediation.

## Scope

This policy covers the Dynamic Location Menus WordPress plugin codebase in this repository.

Out of scope:

- Vulnerabilities in WordPress core, themes, or unrelated plugins
- Server or hosting misconfiguration
- Social engineering attacks

## Security Practices

This plugin follows common WordPress security practices:

- Direct file access is blocked with `ABSPATH` checks
- Admin settings require the `manage_options` capability
- Admin actions use WordPress nonces (`check_admin_referer`)
- Settings input is sanitized before storage
- Output is escaped in the admin UI

## Updates

Updates are read from the public GitHub repository. This plugin does not ship a GitHub token, and a private repository is not a supported update source.
