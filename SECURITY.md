# Security policy

This starter exists to make the safe path the easy path. If you find a way
around one of its controls, please report it privately.

## Reporting

Email rick@claritee.online with:

- what you found and where in the code,
- steps or a request that reproduces it,
- what you think the impact is.

You will get an acknowledgement within three business days. Please do not open
a public issue for anything that could be exploited before a fix is available.

## Scope

In scope: anything that lets a token read data outside its scope, reach a
disabled write tool, bypass rate limits, evade the audit log, or complete the
OAuth flow without the resource owner's consent.

Out of scope: the sample dataset itself, and deployments that removed a control
this repository ships with.
