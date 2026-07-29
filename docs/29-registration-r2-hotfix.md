# Registration R2 Hotfix

## Purpose

Ensure central registration master navigation is visible and guarantee that the dynamic examination connection is configured before registration route binding and validation.

## Middleware ordering rule

Any module backed by an examination database must execute these steps before binding an examination model or running a database validation rule:

1. Start the web session.
2. Resolve the selected examination.
3. Configure the runtime `exam` connection.
4. Continue to route binding, request validation, and controller execution.

This rule prevents accidental central-database queries and `Database connection [exam] not configured` exceptions.
