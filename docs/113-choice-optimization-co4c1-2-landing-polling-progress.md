# Choice Optimization CO4C1.2 — Historical Landing Polling & Progress

- Historical Pull/Re-pull jobs continue to run independently on the queue.
- Choice Optimization landing now displays an indeterminate progress bar for every queued/running Historical BCS source.
- The landing page polls each active source's existing JSON status endpoint every 1.5 seconds.
- Status and matched/review/no-match counts update live.
- After at least one source was observed running and all running sources finish, the landing page performs one automatic reload.
- There is no periodic full-page refresh while jobs are running.
