# Animation System

**HARD RULE — NO FINAL STATE BEFORE ANIMATION COMPLETES:**
The user must NEVER see the final/new state before the animation that transitions to it has finished. The visual flow is always: **previous state displayed → animation plays → final state revealed**. This applies to ALL game elements: cards in hand, table cards, scores, deck count, briscola card, captured counts, turn indicator, trick results — everything. Any element that appears, disappears, or changes value must do so ONLY after the corresponding animation completes. Violating this rule (e.g., a card appearing in hand before the deal animation reaches it, or the briscola card being visible before the initial deal finishes) is a **critical bug**.

**INVARIANT: `store.commitState()` is called ONLY AFTER all animations finish. NO EXCEPTIONS.**

All DOM changes during animation are imperative (`el.remove()`, `el.style`, `document.createElement`, `appendChild`). Vue re-renders via `commitState` only at the very end. This ensures scores, turn indicator, captured decks, deck count, and opponent hand count never update before the visual animation completes.

**Deal animation exception**: `commitState` is called at the START of `runDealAnimation`, but with hiding flags (`dealHiding`, `dealHidingTable`, `dealHidingBriscola`) that render new elements with `opacity:0`. Card-back clones animate from the deck, and each real card is revealed (`opacity:1`) only when its clone arrives. The briscola card fades in after all hand cards are dealt. This preserves the invariant visually.

**Briscola partial deal**: After a trick in Briscola, only 1 card per player is drawn. The `dealHiding` flag IS set (hiding ALL hand cards via reactive binding) to prevent the newly drawn card from flashing visible before the animation. Old cards are revealed imperatively (`el.style.opacity = '1'`) in TWO places: first in `handleTurnResult` immediately after `nextTick` (BEFORE the FLIP animation, so they stay visible during FLIP), and again in `runDealAnimation` after layout (idempotent, same value). Only the newly drawn cards stay hidden and animate from the deck. New cards are identified by card identity (suit-value) comparison against the pre-trick hand stored in `DealContext`.

## Event Flow
```
Mercure turn-result  → handleTurnResult(data)         [sets animating=true]
Mercure game-state   → stashState(newState)            [buffers, no render]
                     → runPlaceAnimation/runCaptureAnimation
                     → commitState (inline)            [Vue re-renders, animating stays true]
                     → FLIP rearrangement              [animating=true guards reflow]
                     → animating=false, post-anim delay (200ms)
                     → commit any stashed pendingState  [if arrived during delay]
                     → processQueue()                  [handle queued events]
```

Events that arrive while busy (`store.animating || inPostAnimDelay`) are queued via `store.queueEvent()` and processed after animation via `processQueue()` → `store.shiftEvent()`.

**Event queuing rules:**
- `turn-result`, `round-end`, `game-over`: always queued when busy
- `game-state`: **stashed** if the queue is empty (for the current animation's commit), **queued** if there are already queued events (preserves ordering for multiple turns)
- `animating` stays `true` through the entire animation + FLIP reflow, then transitions to `inPostAnimDelay` for the 200ms gap. `canPlay` checks `animating` (blocks during FLIP) but not `inPostAnimDelay` (responsive clicks)

**processQueue chaining:**
- After `turn-result`: `handleTurnResult` runs animation, which calls `processQueue` at the end
- Before `turn-result` from queue: peeks at next event; if `game-state`, pre-stashes it so `finishAnimation` can commit the correct intermediate state
- After `game-state` (non-deal): commits state, then **chains** to `processQueue` for remaining events
- After `round-end`/`game-over`: shows overlay, chains to `processQueue`
- `runDealAnimation` early return (no deck rect): calls `processQueue` to avoid stuck events

## Place Animation
1. Hide source card in hand (`visibility: hidden`)
2. Snapshot table + hand positions
3. Imperatively create card element on table (hidden, `opacity: 0`)
4. Imperatively remove played card from hand
5. FLIP existing table + hand cards to new positions
6. Clone animates from hand to table in animation layer (500ms)
7. Reveal real card, remove clone
8. **commitState** — scores, turn indicator, etc. update

## Capture Animation
1. Hide source card, create clone in animation layer
2. Clone slides to landing card on table (500ms)
3. Pause (150ms), glow captured cards (500ms)
4. Snapshot positions, imperatively remove captured cards + played card
5. FLIP remaining cards
6. Sweep clones to captured deck (450ms, 100ms stagger)
7. **commitState** — captured deck count, scores, etc. update

## Deal Animation (Full Deal — Initial + New Round)
1. `dealHiding` + `dealHidingTable` + `dealHidingBriscola` (Briscola) flags set to true
2. commitState — Vue renders cards with `opacity: 0` (via `:style` binding)
3. `store.animating = true` — blocks all incoming events
4. Card-back wrappers animate from deck to each card position (350ms, 150ms stagger)
5. Each wrapper arrival reveals the real card (`opacity: 1`)
6. Briscola card fades in (300ms) after all hand cards are dealt
7. Clear hiding flags, `await nextTick()` (Vue removes reactive `opacity:0`), THEN clear imperative opacity
8. Set `animating = false`, process any pending events

**CRITICAL: The `nextTick` between clearing hiding flags and clearing imperative opacity is mandatory.** Without it, clearing the imperative `opacity: 1` exposes Vue's stale reactive `opacity: 0` for one frame, causing cards to flash invisible.

## Deal Animation (Briscola Partial — After Trick)
1. `dealHiding` IS set — ALL hand cards render at `opacity: 0` (prevents new card flash)
2. commitState — all cards hidden via reactive binding
3. After layout (nextTick + rAF), old cards revealed immediately (`el.style.opacity = '1'`)
4. Only the newly drawn cards (1 per player) stay hidden and animate from the deck
5. Each wrapper arrival reveals the new card (`opacity: 1`)
6. Clear hiding flags, `await nextTick()`, clear imperative opacity, set `animating = false`, process queue

## Animation Timings
| Animation | Duration | Easing |
|---|---|---|
| Place/capture slide | 500ms | cubic-bezier(0.22, 0.61, 0.36, 1) |
| Table FLIP | 500ms | ease-out |
| Hand FLIP | 400ms | ease-out |
| Capture pause | 150ms | — |
| Capture glow | 500ms | — |
| Sweep to captured | 450ms, 100ms stagger | ease-in-out |
| Sweep scale (desktop) | none (75→75, same size) | — |
| Sweep scale (mobile) | 0.689 (58→40) | scale × fromW |
| Deal slide | 350ms | cubic-bezier(0.22, 0.61, 0.36, 1) |
| Deal scale (desktop) | none (75→75, same size) | — |
| Deal scale (mobile) | 1.45 (40→58) | scale × fromW |
| Deal stagger (hands) | 150ms | — |
| Deal stagger (table) | 75ms | — |
| Scopa flash | 1500ms | scopaFlash keyframe |
| afterAnimation delay | 200ms | — |
| Safety timeout | 10000ms | — |

## Reconciliation Animation (`smoothCommit`)

When the client recovers from a missed SSE event (reconnection, polling divergence), `reconcileState` → `smoothCommit` provides a smooth visual transition using FLIP:

1. Snapshot current card positions by identity (table: `t:value-suit`, hand: `h:value-suit`, opponent: `o:N`)
2. `commitState(freshState)` — Vue re-renders to the new state
3. `flipRearrange(before)` — animate surviving cards from old→new positions (new cards pop in, removed cards disappear)

**Key constraints:**
- **No deal animation for trick draws**: Only triggers `runDealAnimation` when hands go from empty to populated (genuine new round). The `isDealState` deckCount heuristic is NOT used — it false-triggers on Briscola/Tressette trick draws, making cards incorrectly fly from the draw pile.
- **Stale event flush**: After FLIP completes, `pendingEvents` and `pendingState` are flushed. SSE reconnection may re-deliver events (via `Last-Event-ID`) that are already reflected in the reconciled state. Processing them would cause wrong animations and state regressions.
- **`oppPlayedIdx` not available**: Unlike normal turn-result animations, reconciliation has no `TurnResult` data, so `flipRearrange` runs without `oppPlayedIdx`. Opponent hand cards don't slide to fill gaps — they snap into position. This is a cosmetic trade-off for the recovery path.

See `communication-layer.md` § "State Reconciliation" for the full pipeline.

## FLIP Utilities (flipUtils.ts)

Helper functions for the animation system:
- `snapshotPositions(container, selector)` — Records DOMRect positions keyed by `data-card-key`
- `animateFLIP(before, after, container, selector, duration, easing)` — Calculates deltas and animates using Web Animations API
- `createCardClone(card, rect, deckStyle)` — Creates a fixed-position DOM element with card face image
- `createCardBackClone(rect, deckStyle)` — Same but for card backs (deal animation)
- `animateClone(clone, from, to, duration, easing, scale?)` — Animates clone between two DOMRects
- `cardKey(card, index?)` — Generates stable key string for FLIP tracking
- `sleep(ms)` — Promise-based delay