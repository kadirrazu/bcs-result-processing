# CRUD vs Action Matrix

## Purpose
This matrix prevents complex business operations from being forced into generic CRUD controllers.

## Classification Rule
Use conventional CRUD only when the operation directly creates, reads, updates, or deletes one primary resource without a significant workflow.

Use an Action when the operation:
- changes multiple records or aggregates
- enforces business workflow or state transitions
- requires transactions
- must be idempotent or retryable
- creates audit or processing-run records
- can be triggered from HTTP, CLI, queue, or scheduler

## Matrix
| Capability | CRUD | Action | Service | Notes |
|---|---:|---:|---:|---|
| Manage designations | Yes | Optional | Rarely | Standard master-data CRUD |
| Manage users | Yes | Yes | Sometimes | Create/update actions enforce security and audit |
| Register examination | Yes | Yes | Yes | Database provisioning is an explicit action |
| Edit examination metadata | Yes | Optional | Sometimes | State transitions remain actions |
| Provision examination database | No | Yes | Yes | Infrastructure workflow |
| Import candidates | No | Yes | Yes | Batch, validation, audit, retry |
| Import preliminary marks | No | Yes | Yes | Batch workflow |
| Generate mark distribution | No | Yes | Yes | Reproducible analytical operation |
| Save draft cut-off | Yes | Optional | Sometimes | Simple persistence if no approval occurs |
| Approve cut-off | No | Yes | Yes | Controlled state transition |
| Process preliminary result | No | Yes | Yes | High-impact deterministic processing |
| Publish preliminary result | No | Yes | Yes | Approval/publication workflow |
| Import written marks | No | Yes | Yes | Batch workflow |
| Process written result | No | Yes | Yes | Domain-rule orchestration |
| Generate merit | No | Yes | Yes | Versioned processing run |
| Optimize choices | No | Yes | Yes | Complex deterministic workflow |
| Run allocation | No | Yes | Yes | Existing engine through adapter |
| View reports | Read-only | Optional | Yes | Query/report services where complexity exists |
| Retry failed processing run | No | Yes | Yes | Idempotent operational action |

## Controller Guidance
Resource controller methods:
```text
index, create, store, show, edit, update, destroy
```

Workflow controller methods should delegate immediately to a named Action:
```text
approve, process, publish, retry, archive
```

Do not create ambiguous methods such as `doProcess()` or `handleData()`.
