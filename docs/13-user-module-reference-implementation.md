# User Module Reference Implementation

## Purpose

The User module is the first reference CRUD module for the BCS Result Processing Platform. It demonstrates the approved request-to-application-layer flow without introducing a repository where Eloquent already provides a sufficient persistence abstraction.

## Request Flow

```text
Route
  -> Controller
  -> Form Request
  -> DTO
  -> Action or Query Object
  -> Eloquent Model / Database
```

## Responsibilities

### Controller

- Authorizes page-level operations.
- Converts validated request data into `UserData`.
- Invokes one application Action or Query object.
- Returns an HTTP response.
- Contains no persistence or business-rule implementation.

### Form Request

- Authorizes mutation requests.
- Validates input shape and database references.
- Allows an existing inactive designation to remain selected during an edit.

### UserData DTO

- Carries normalized, typed application input.
- Converts role strings into `UserRole` enum values.
- Omits an empty password during updates.

### Actions

- `CreateUserAction` creates a user transactionally.
- `UpdateUserAction` updates a user transactionally and prevents self-deactivation.

### Query Objects

- `ListUsersQuery` owns user search, eager loading and pagination.
- `GetAssignableDesignationsQuery` owns designation selection rules.

## Repository Decision

No User repository is introduced at this stage. User persistence is simple Eloquent CRUD and query composition. A repository should be introduced only when it provides a real boundary, such as multiple data sources, complex persistence orchestration, or a stable domain-facing contract.

## Testing Contract

Feature tests must verify:

- Authorized administrators can create and update users.
- Password remains unchanged when omitted during update.
- An administrator cannot deactivate their own account.
- User directory search works through designation names.
- Existing authorization tests continue to pass.

## Reference Rule

Future CRUD modules should follow this structure unless an Architecture Decision Record explicitly documents a justified exception.
