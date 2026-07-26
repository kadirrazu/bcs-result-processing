# Security and Authorization

## Status

**Approved — Foundation v1.0**

## Authentication Rules

- Authentication is provided by Laravel Fortify.
- Only active central application users may authenticate.
- Failed inactive-user authentication must not reveal that the account exists.
- Login attempts remain rate limited.
- A successful login records `last_login_at`.

## Authorization Rules

- Authentication and authorization are separate controls.
- Routes protected only by `auth` are not automatically permitted for every authenticated user.
- Policies are the primary authorization mechanism for model-oriented capabilities.
- Form Requests must authorize the requested write operation.
- Controllers must authorize read screens and route entry points.
- Navigation visibility is only a usability control; server-side authorization remains mandatory.

## User Administration

User administration is restricted to active users with the `admin` role.

- Admin: may list, create, view, and update users.
- Operator: may not access user administration.
- Viewer: may not access user administration.
- A user may not deactivate their own currently authenticated account.

## Data Boundaries

Central application users are stored in the central database. Candidate and examination processing data remain in the selected examination database under ADR-001.

## Future Controls

Later milestones will add:

- permission capabilities beyond broad roles;
- sensitive-action audit events;
- password policy hardening;
- session and account lockout policy;
- optional two-factor authentication;
- authorization rules for examination context and processing stages.
