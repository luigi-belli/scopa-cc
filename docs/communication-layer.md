# Client-Server Communication Layer

Deep analysis of the real-time communication architecture, event processing pipeline, resilience mechanisms, and the periodic polling safety net.

## Architecture Overview

```
Browser                          Server
┌──────────────────┐      ┌──────────────────┐
│  GameScreen.vue  │      │  API Platform     │
│  (orchestrator)  │      │  Processors       │
│        │         │      │       │           │
│  ┌─────┴──────┐  │      │  ┌────┴────────┐  │
│  │ gameStore   │  │      │  │ GameEngine   │  │
│  │ (Pinia)    │  │      │  │ (strategy)   │  │
│  └────────────┘  │      │  └─────────────┘  │
│        │         │      │       │           │
│  ┌─────┴──────┐  │ REST │  ┌────┴────────┐  │
│  │ useApi.ts  │──┼──────┼──│ Providers/  │  │
│  │            │  │      │  │ Processors  │  │
│  └────────────┘  │      │  └─────────────┘  │
│        │         │      │       │           │
│  ┌─────┴──────┐  │ SSE  │  ┌────┴────────┐  │
│  │ useMercure │◄─┼──────┼──│ Mercure     │  │
│  │            │  │      │  │ Publisher   │  │
│  └────────────┘  │      │  └─────────────┘  │
└──────────────────┘      └──────────────────┘
```

Two communication channels:
1. **REST API** (`useApi.ts`): Client→server actions (play card, select capture, etc.)
2. **Mercure SSE** (`useMercure.ts`): Server→client push (state updates, turn results, etc.)

Three resilience layers:
1. **API retry** with exponential backoff + timeout on every request
2. **SSE handler guards** + staleness detection with auto-reconnect
3. **Periodic state polling** safety net to catch any missed events

## REST API Client (`useApi.ts`)

### Request Flow
- Two-function architecture: `request()` (retry loop) delegates to `doRequest()` (single attempt)
- Sets `Content-Type: application/json`, `Accept: application/json`
- Injects `X-Player-Token` from Pinia store (if available)
- Base URL: `/api{path}`

### Timeout
- Every request gets a 10s `AbortController` timeout (configurable via `RetryOptions.timeout`)
- Timer is always cleared in `finally` block to prevent leaks

### Retry Logic
- Configurable `maxRetries` per call (default 0 = no retries)
- **Exponential backoff**: base delay 500ms (`RETRY_BASE_DELAY`), doubles each attempt
- **Jitter**: 20% random jitter added to prevent thundering herd
- **`isRetryable(error)`** (exported, tested): determines which errors warrant retry
  - `true`: 5xx server errors, 429 rate limit, `TypeError` (network failure), `AbortError` (timeout)
  - `false`: 4xx client errors (400, 403, 409) — deterministic failures, no point retrying

### Per-Endpoint Retry Config
| Method | Retries | Rationale |
|---|---|---|
| `getState()` | 2 | Critical for reconnection and polling — must be reliable |
| `playCard()` | 1 | Idempotent on server (409 on duplicate) — safe to retry once |
| `selectCapture()` | 1 | Same — 409 guards against double-select |
| `nextRound()` | 1 | Same — 409 guards against double-advance |
| `heartbeat()` | 0 | Fires every 10s — next tick covers a transient failure |
| `leaveGame()` | 0 | Best-effort — caller always navigates away regardless |
| `createGame()` | 0 | Not idempotent — could create duplicate games |
| `lookupGame()` | 0 | Not critical — user can retry manually |
| `joinGame()` | 0 | Not idempotent — could cause race conditions |

### Error Handling
- **409 Conflict**: Parses JSON for `error.conflict` or `error.gameNameTaken` keys, translates to i18n
- **403 Forbidden**: Translates as `api.accessDenied`
- **Other errors**: Returns `status + statusText` via i18n template

### API Methods
| Method | HTTP | Path |
|---|---|---|
| `createGame()` | POST | `/games` |
| `lookupGame()` | GET | `/games/lookup?name=...` |
| `joinGame()` | POST | `/games/{id}/join` |
| `getState()` | GET | `/games/{id}` |
| `playCard()` | POST | `/games/{id}/play-card` |
| `selectCapture()` | POST | `/games/{id}/select-capture` |
| `nextRound()` | POST | `/games/{id}/next-round` |
| `heartbeat()` | POST | `/games/{id}/heartbeat` |
| `leaveGame()` | POST | `/games/{id}/leave` |

## Mercure SSE Client (`useMercure.ts`)

### Connection
- Creates `EventSource` to `/.well-known/mercure?topic={encoded_topic}`
- Topic per player: `/games/{gameId}/player/{playerIndex}`
- Tracks `hasConnectedBefore` flag to detect reconnections
- Stores `savedPlayerIndex` for automatic reconnection after stale timeout

### Handler Safety (`safeCall`)
Module-level function that wraps every SSE event handler:
```typescript
function safeCall(fn: () => unknown): void {
  try {
    const result = fn()
    if (result instanceof Promise) {
      result.catch(e => console.error('Mercure handler async error:', e))
    }
  } catch (e) {
    console.error('Mercure handler error:', e)
  }
}
```
- **Sync exceptions**: Caught and logged — never break the EventSource message loop
- **Async rejections**: Promise `.catch()` prevents unhandled rejection killing the pipeline
- All six event listeners (`game-state`, `turn-result`, `choose-capture`, `round-end`, `game-over`, `opponent-disconnected`) and `onReconnect` go through `safeCall`

### Staleness Detection
- **`STALE_TIMEOUT = 45_000`** (45s): If no SSE event arrives within this window, the connection is considered stale (zombie)
- **`touchActivity()`**: Called on every incoming event and on `onopen` — resets the staleness timer
- **On stale**: Closes EventSource, sets `connected = false`, schedules `connect(savedPlayerIndex)` after 1s delay
- Captures `savedPlayerIndex` into a local const before the async reconnect callback to avoid non-null assertion

### Reconnection
- `onopen`: Sets `connected=true`; if reconnecting (`wasDisconnected && hasConnectedBefore`), calls `onReconnect()` via `safeCall`
- `onerror`: Sets `connected=false`; relies on browser's native EventSource auto-retry for transient disconnects
- **Stale timeout**: Application-level reconnection for zombie connections that browser can't detect

### Event Types
| Event | Handler | Data |
|---|---|---|
| `game-state` | `onGameState(data)` | Full `GameState` object |
| `turn-result` | `onTurnResult(data)` | Turn outcome + animation data |
| `choose-capture` | `onChooseCapture(data)` | Capture options |
| `round-end` | `onRoundEnd(data)` | Round scores |
| `game-over` | `onGameOver(data)` | Final scores, winner |
| `opponent-disconnected` | `onOpponentDisconnected()` | No data |

### Parse Safety
- JSON parse wrapped in try/catch; parse errors silently return `null`
- Extracts `payload.data ?? payload` for backwards compatibility

### Cleanup
- `disconnect()`: Clears stale timer, nulls `savedPlayerIndex`, closes EventSource
- `onUnmounted`: Calls `disconnect()` automatically

## Game State Management (`gameStore.ts`)

### Three-Layer State Model
```
serverState    ← latest state from server (always up to date)
pendingState   ← buffered state waiting for animation to finish
displayState   ← what Vue templates render (never updated during animation)
```

### State Transitions
- **`commitState(state)`**: Sets `displayState = serverState = state`, clears `pendingState`. Used when NOT animating.
- **`stashState(state)`**: Sets `serverState = pendingState = state`. Display unchanged. Used when animation is in progress.
- **`finishAnimation()`**: Promotes `pendingState → displayState`, clears `animating` flag.

### Event Queue
- `pendingEvents: Array<{type, data}>` — FIFO queue for SSE events received while busy
- `queueEvent(ev)` / `shiftEvent()` — push/pop operations

### Session Persistence
- `setGame()`: Writes `{gameId, playerToken, myIndex}` to `localStorage('scopa-active-game')`
- `restoreSession()`: Reads it back on mount/reload
- `clearSession()`: Removes localStorage entry
- `$reset()`: Clears all Pinia state + localStorage

## Event Processing Pipeline (`GameScreen.vue`)

### Busy Guard
```typescript
const isBusy = () => store.animating || inPostAnimDelay.value
```
All SSE handlers check `isBusy()` first — if busy, events are queued.

### Handler Routing

**`onTurnResult`**: If busy → queue; else → `handleTurnResult(data)` (triggers animation)

**`onGameState`**: Three branches:
1. Busy + has pending events → queue (preserves ordering)
2. Busy + no pending events → `stashState(data)` (display stays, server state updates)
3. Not busy → `maybeCommitOrDeal(data)` (immediate render or deal animation)

**`onRoundEnd` / `onGameOver`**: If busy → queue; else → await handler, then `processQueue()`

**`onReconnect`**: Fetches fresh state via `api.getState()` (with built-in retries), routes through `reconcileState()`. On failure, schedules deferred reconcile via `scheduleReconcile()`.

### Event Queue Processing
```
processQueue()
  ├── turn-result → await handleTurnResult(data)  [awaited, errors caught → clears lock + drains queue]
  ├── game-state  → maybeCommitOrDeal(data)       [chains processQueue if not animating]
  ├── round-end   → await handleRoundEnd(data)    [chains processQueue]
  └── game-over   → await handleGameOver(data)    [terminal, no chain]
```

The `turn-result` branch is `await`ed and wrapped in try/catch. On error, `store.animating` and `inPostAnimDelay` are cleared, and `processQueue()` is called recursively to drain remaining events. This prevents the critical failure mode where an animation exception kills the entire pipeline.

### Animation Safety Timeout
- 10-second timeout (`SAFETY_MS = 10000`) on every `handleTurnResult` call
- If animation exceeds 10s: force-restores styles, clears animation layer, commits pending state
- Calls `processQueue()` to unblock pipeline

### Deal Detection
```typescript
function isDealState(prev, next): boolean {
  if (!prev) return true                                    // First load
  if (prev.myHand.length === 0 && next.myHand.length > 0) return true  // Empty hand → dealt
  if (next.deckCount < prev.deckCount) return true          // Deck shrank
  return false
}
```

Used by `maybeCommitOrDeal()` in the normal SSE event path. **Not** used in the reconciliation path — see `smoothCommit` below.

## API Action Handlers

### `handlePlayCard`
- Guards with `canPlay` computed + `playInFlight` flag
- Lifts card visually before API call
- On error: resets card visual, logs error, **schedules `reconcileState`** (server may have processed the move despite network error)

### `handleSelectCapture`
- Dismisses overlay BEFORE API call (optimistic UI for smooth animation)
- On API failure: **restores `showCaptureChoice` overlay** so the player can retry

### `handleNextRound`
- Checks `serverState` first — if the opponent already advanced, skips the API call entirely
- On success: dismisses overlay + `lastRoundScores`, consumes any stashed state
- On API failure: re-checks `serverState` (event may have arrived during the call); if still stuck, overlay stays visible for retry

## State Reconciliation (Safety Net)

### `reconcileState(freshState)`
Routes a fresh API-fetched state through the pipeline, superseding any buffered events:
1. If busy → queues as `game-state` event (will be processed when animation finishes)
2. **Flushes stale events**: Clears `pendingEvents` and `pendingState` — the API-fetched state is authoritative and supersedes anything buffered (e.g. leftover SSE events from a previous connection)
3. Detects `round-end` / `game-over` states that were missed — shows the appropriate overlay
4. Re-enables capture choice overlay if server is in `choosing` state
5. Delegates to `smoothCommit(freshState)` for smooth FLIP-based transition

### `smoothCommit(state)`
Used exclusively by `reconcileState` for smooth state transitions during recovery:
- **No previous state**: Falls through to `maybeCommitOrDeal()` (initial load / deal animation)
- **Genuine new round** (hands empty → populated): Triggers `runDealAnimation()`
- **Normal state change**: FLIP-based commit — snapshots positions, commits state, animates surviving cards from old→new positions
- **Stale event flush**: After FLIP animation completes, flushes `pendingEvents` and `pendingState` before calling `processQueue()`. This prevents stale SSE events (re-delivered via `Last-Event-ID` after reconnection, already reflected in the reconciled state) from causing wrong animations or state regressions.

**Important**: `smoothCommit` does NOT use `isDealState()`. The generic `isDealState` check includes a `deckCount` heuristic that false-triggers on Briscola/Tressette trick draws (deck shrinks after every trick), causing cards to incorrectly animate from the draw pile. Only the hand-empty→populated check is used for deal detection in reconciliation.

### `scheduleReconcile(delayMs = 3000)`
Schedules a deferred state reconciliation. Used by:
- `onReconnect` when the initial `getState()` fetch fails
- `handlePlayCard` on API error (server may have processed the move)
- `handleSelectCapture` on API error
- `handleNextRound` on API error

De-duped: only one pending reconcile at a time.

### `pollForMissedState()` — Periodic Polling Safety Net
Runs every **5 seconds** via `setInterval` (started in `onMounted`, cleared in `onUnmounted`).

**Guards** (skip poll when):
- Animation is running (`store.animating`)
- Game is disconnected (`disconnected.value`)
- Game over overlay is showing (`showGameOver`)

**Divergence detection** — compares server response against `store.serverState`:
- `state` (e.g. playing → choosing)
- `isMyTurn` (most common missed-event symptom)
- `deckCount` (deck changed = deal happened)
- `myHand.length` (hand size changed)
- `myCapturedCount` (captures happened)

On divergence: logs a warning and calls `reconcileState(fresh)`.

### Lifecycle Integration
```
onMounted:
  ├── Restore session from localStorage
  ├── Fetch initial state (with retries)
  ├── Run deal animation
  ├── Connect Mercure SSE
  ├── Start heartbeat (10s interval)
  └── Start state poll (5s interval)

onUnmounted:
  ├── Disconnect Mercure
  ├── Clear heartbeat interval
  ├── Clear poll interval
  ├── Clear reconcile timer
  └── Clear nudge timer
```

## Test Coverage

### `useApi.test.ts`
- **`isRetryable`**: Tests all error types — 5xx (retryable), 429 (retryable), 4xx (not retryable), TypeError (retryable), AbortError (retryable), generic Error (not retryable)
- **Retry behavior**: Succeeds immediately, retries on 500, retries on TypeError, no retry on 400/409, exhausts all retries, succeeds on last attempt, no retry when maxRetries=0
- **ApiError**: Captures status code, is instanceof Error

### `useMercure.test.ts`
- **safeCall pattern**: Catches sync exceptions, catches async rejections, passes through successes, handles void returns
- **Staleness detection**: Triggers after 45s, resets on activity, doesn't trigger after disconnect
- **Reconnection timing**: 1s delay after stale detection
- **parseEvent safety**: Valid JSON with/without data wrapper, invalid JSON, empty string, nested data
