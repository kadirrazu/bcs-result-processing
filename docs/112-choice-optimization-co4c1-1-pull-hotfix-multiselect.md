# Choice Optimization CO4C1.1 — Historical Pull Hotfix + Multi-select

- Fixes `Method Illuminate\Database\Eloquent\Collection::getKey does not exist`.
- Root cause: grouped Eloquent Collection values are nested Collections; Eloquent `except()` expects Models.
- Historical rows are converted to a base Support Collection before `groupBy()->except()`.
- Choice Optimization Historical Sources now supports selecting one or multiple EFFECTIVE BCS datasets.
- `Pull / Re-pull Selected` queues one independent job per selected BCS.
- An already-running source is skipped rather than blocking other selected sources.
- Re-pull semantics remain unchanged: the old workspace match snapshot for that BCS is replaced by the fresh result.
