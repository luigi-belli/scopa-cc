# Scopa - Italian Card Game

## Overview

Web-based two-player Scopa card game with real-time multiplayer and single-player AI mode. Uses traditional Italian regional card images on a green casino-table background. Built with API Platform (PHP/Symfony), Vue 3 + TypeScript, PostgreSQL, and Mercure SSE for real-time.

## Tech Stack

- **Backend**: PHP 8.4 + Symfony 7.3 + API Platform 4.1
- **Frontend**: Vue 3.5 + TypeScript 5.8 + Pinia 3 + Vue Router 4.5 (SPA)
- **Database**: PostgreSQL 17 via Doctrine ORM 3 (JSONB for card arrays)
- **Real-time**: Mercure SSE (server→client push), REST API (client→server)
- **AI**: Symfony Messenger async handler with 1.5s delay
- **Containerization**: Docker Compose — 6 services, exposed on port 5982
- **Card assets**: 4 deck styles, each with 40 card face images + card back

## How to Run

```bash
docker compose up --build   # Starts on http://localhost:5982
```

Services: postgres, php (API), messenger-worker (AI), mercure (SSE), nginx (SPA + reverse proxy).

**IMPORTANT: Always use Docker Compose to run all tooling, tests, builds, and commands.** No Node.js, PHP, or Composer is installed on the host machine. All commands must run inside containers.

```bash
# Backend PHPUnit tests
docker compose exec php bin/phpunit

# Frontend tests (vitest)
docker run --rm -v "$(pwd)/frontend:/app" -w /app node:22-alpine sh -c "npm install && npx vitest run"

# Frontend build check
docker run --rm -v "$(pwd)/frontend:/app" -w /app node:22-alpine sh -c "npm install && npm run build"

# Symfony console commands
docker compose exec php bin/console <command>
```

## Project Structure

```
scopa/
  docker-compose.yml           # 6 services, port 5982
  
  api/                         # API Platform (PHP/Symfony)
    Dockerfile                 # PHP 8.4 CLI + composer + entrypoint
    entrypoint.sh              # cache:clear, migrations, messenger:setup
    composer.json
    .env                       # DATABASE_URL, MERCURE_*, APP_SECRET
    .env.test                  # Test environment overrides
    bin/
      console                  # Symfony console entrypoint
    config/
      bundles.php
      routes.yaml
      services.yaml            # Autowiring + Mercure parameters
      packages/
        api_platform.yaml      # JSON format, /api prefix, no graphql
        cache.yaml
        doctrine.yaml          # PostgreSQL + UUID type
        doctrine_migrations.yaml
        framework.yaml
        mercure.yaml           # Mercure hub, JWT secret, publish permissions
        messenger.yaml         # Doctrine transport for async AI
        test/
          framework.yaml       # Test-specific framework config
    migrations/
      Version20260405000001.php  # games table (26 columns)
    src/
      Kernel.php
      Entity/
        Game.php               # Single entity: all game state in one table + ApiResource operations
      Enum/
        GameState.php          # waiting|playing|choosing|round-end|game-over|finished
        DeckStyle.php          # piacentine|napoletane|toscane|siciliane
        Suit.php               # Denari|Coppe|Bastoni|Spade (with letter() method)
      Dto/
        Input/
          CreateGameInput.php  # playerName, gameName?, singlePlayer, deckStyle
          JoinGameInput.php    # playerName
          PlayCardInput.php    # cardIndex
          SelectCaptureInput.php # optionIndex
        Output/
          CreateGameOutput.php # gameId, playerToken, state, gameState?
          JoinGameOutput.php   # gameId, playerToken, gameState
          GameStateOutput.php  # Full player-specific game state (20 fields)
          GameLookupOutput.php # id, name, state
      Service/
        GameEngine.php         # Core game logic
        DeckService.php        # Deck creation, Fisher-Yates shuffle
        ScoringService.php     # 5-category round scoring
        AIService.php          # AI move evaluation
        MercurePublisher.php   # Publishes SSE events via Symfony Mercure HubInterface
        PlayerTokenService.php # Token generation, name sanitization
      State/
        Provider/
          GameStateProvider.php   # GET /games/{id} — player-specific state
          GameLookupProvider.php  # GET /games/lookup — find waiting game by name
        Processor/
          CreateGameProcessor.php    # POST /games
          JoinGameProcessor.php      # POST /games/{id}/join
          PlayCardProcessor.php      # POST /games/{id}/play-card
          SelectCaptureProcessor.php # POST /games/{id}/select-capture
          NextRoundProcessor.php     # POST /games/{id}/next-round
          HeartbeatProcessor.php     # POST /games/{id}/heartbeat
          LeaveGameProcessor.php     # POST /games/{id}/leave
      Command/
        CleanupGamesCommand.php  # app:cleanup-games — delete inactive games (>10 min)
      Message/
        HandleAITurnMessage.php
      MessageHandler/
        HandleAITurnHandler.php  # Async AI with 1.5s delay
    public/
      index.php                # Symfony front controller
    tests/
      Unit/
        Service/
          DeckServiceTest.php
          GameEngineTest.php
          ScoringServiceTest.php
          AIServiceTest.php
          PlayerTokenServiceTest.php
      Integration/             # (directory exists, tests to be added)
    phpunit.xml

  frontend/                    # Vue 3 + TypeScript SPA
    package.json
    tsconfig.json
    vite.config.ts
    index.html
    public/
      favicon.svg
      assets/cards/            # Card images (4 deck styles)
        piacentine/            # 40 files + bg (SVG placeholder content in .jpg extension)
        napoletane/            # 40 files + bg (SVG placeholder content in .jpg extension)
        toscane/               # 40 files + bg (SVG placeholder content in .png extension)
        siciliane/             # 40 files + bg (SVG placeholder content in .png extension)
    src/
      main.ts                  # Vue app + Pinia + Router setup
      vite-env.d.ts            # TypeScript declarations for .vue modules
      App.vue                  # Root component (router-view)
      types/
        card.ts                # Card, DeckStyle, SUIT_LETTER, DECK_EXT, cardImagePath, cardBackPath
        game.ts                # GameState, TurnResult, RoundEndData, CreateGameResponse, etc.
      stores/
        gameStore.ts           # Pinia: displayState/serverState separation, event queue
      i18n/
        index.ts               # useI18n() composable: reactive locale, t() translator, setLocale()
        it.ts                  # Italian translations (source of truth for keys)
        en.ts                  # English translations
      composables/
        useApi.ts              # REST client with X-Player-Token auth
        useMercure.ts          # SSE subscription with typed event handlers
        useDeckStyle.ts        # Deck selection + localStorage persistence
      animations/
        flipUtils.ts           # FLIP helpers: snapshot, animateFLIP, createCardClone, animateClone
      components/
        screens/
          LobbyScreen.vue      # Create/join/single-player, language selector
          WaitingScreen.vue    # Waiting for opponent with Mercure subscription
          GameScreen.vue       # Game board + animation orchestration + event processing
        game/
          CardComponent.vue    # Single card face (playable, glow states)
          CardBack.vue         # Card back
          TurnIndicator.vue    # "Il tuo turno" / "Turno avversario"
          DeckVisual.vue       # Draw pile (absolute positioned, opacity fade when empty)
          CapturedDeck.vue     # Captured pile (absolute positioned, count badge)
        overlays/
          CaptureChoiceOverlay.vue  # Capture option selection
          RoundEndOverlay.vue       # Round scores + next round button
          GameOverOverlay.vue       # Final scores, winner, back to lobby
          ScoreTable.vue            # 5-category breakdown with manual esc() for safety
        effects/
          ScopaFlash.vue       # 1500ms "SCOPA!" flash animation
          ConfettiCanvas.vue   # Canvas-based confetti for game winner
          DisconnectBanner.vue # Fixed banner z-500
        lobby/
          DeckSelector.vue     # 4 deck style visual selector
      css/
        style.css              # Layout, lobby, game board, responsive
        cards.css              # Card dimensions, hover, glow
        animations.css         # Win/loss/scopa/disconnect keyframes

  nginx/
    Dockerfile                 # Multi-stage: builds frontend with Node, serves via nginx
    default.conf               # Reverse proxy: SPA, /api/, /.well-known/mercure
```

## Game Rules (Traditional Scopa)

### The Deck
- **40 cards** in 4 suits: **Denari** (coins), **Coppe** (cups), **Bastoni** (clubs), **Spade** (swords)
- Values 1-10 per suit (8=Fante/Jack, 9=Cavallo/Knight, 10=Re/King)
- Card image naming: `{value}{suit_letter}.{ext}` where suit letters are: `d`=Denari, `c`=Coppe, `b`=Bastoni, `s`=Spade

### Deal
- 4 cards dealt face-up to the table
- 3 cards dealt to each player
- When both players' hands are empty and the deck has cards, deal 3 more to each (no new table cards)
- The non-dealer goes first; dealer alternates each round

### Turn Flow
1. Player plays one card from their hand
2. **Capture priority rule**: If the played card's value matches any single table card, **must capture that card** (cannot choose a multi-card sum instead)
3. If multiple single-card matches exist, player chooses which one to capture
4. If no single-card match but a combination of table cards sums to the played value, capture those cards
5. If multiple sum combinations exist, player chooses which set
6. If no captures possible, card is placed on the table
7. **Scopa**: If a capture clears all cards from the table, that's a scopa (worth 1 point). Does NOT count on the very last play of a round.
8. **End of round**: When all cards are played and deck is empty, the last player to capture gets remaining table cards (not a scopa)

### Scoring (per round)
| Category | Rule |
|---|---|
| **Carte** | Most total captured cards (1 point, 0 if tied) |
| **Denari** | Most Denari-suit cards captured (1 point, 0 if tied) |
| **Sette Bello** | Captured the 7 of Denari (always 1 point to holder) |
| **Primiera** | Highest sum of best primiera-value card per suit (1 point) |
| **Scope** | 1 point per scopa made during the round |

### Primiera Values
| Card Value | Primiera Points |
|---|---|
| 7 | 21 |
| 6 | 18 |
| 1 (Asso) | 16 |
| 5 | 15 |
| 4 | 14 |
| 3 | 13 |
| 2 | 12 |
| 8, 9, 10 | 10 |

Must have all 4 suits to compete. If one player has all 4 and the other doesn't, the one with all 4 wins. If neither has all 4, no point awarded.

### Winning
- Game to **11 points** across multiple rounds
- If both reach 11+ with the same score, play continues until clear lead

## Playing Modes

### Multiplayer (2 players)
1. Player 1 creates a game (name + game name)
2. Player 1 waits for opponent (Mercure SSE subscription)
3. Player 2 joins by name + game name (lookup API + join API)
4. Only 2 players per game
5. Disconnect mid-game = opponent wins immediately

### Single Player (vs AI "Claude")
1. Player clicks "Gioca contro Claude"
2. API creates game with `singlePlayer: true` + starts round immediately
3. AI plays via Symfony Messenger async handler (1.5s delay)
4. AI auto-selects capture when multiple options exist

## Card Deck Styles

| Deck | Format | Source | License |
|---|---|---|---|
| **Piacentine** | JPG | OMerkel/Scopa | GPL-3.0 |
| **Napoletane** | JPG | OMerkel/Scopa | GPL-3.0 |
| **Toscane** | PNG | htdebeer/SVG-cards | LGPL-2.1 |
| **Siciliane** | PNG | marcoscarpetta/scopy | — |

Image path: `/assets/cards/{deckStyle}/{value}{suitLetter}.{ext}`
Card back: `/assets/cards/{deckStyle}/bg.{ext}`

Selection saved to `localStorage` key `scopa-deck-style`. In multiplayer, the creator's choice is used.

**Note**: Current card images are SVG-based placeholders (SVG content in .jpg/.png extensions) showing value and suit name on a colored background. Replace with real card images from the GPL-licensed repos listed above for production use.

## Backend Architecture

### No Controllers — API Platform Providers and Processors Only

All API endpoints are defined as **API Platform operations** on the `Game` entity via PHP attributes. There are **no Symfony controllers**. Each endpoint routes to a **State Provider** (for GET) or **State Processor** (for POST).

The `Game` entity (`api/src/Entity/Game.php`) declares all 9 operations in its `#[ApiResource]` attribute:

| Operation | Method | URI Template | Input DTO | Output DTO | Handler Class |
|---|---|---|---|---|---|
| Create | POST | `/games` | `CreateGameInput` | `CreateGameOutput` | `CreateGameProcessor` |
| Get State | GET | `/games/{id}` | — | `GameStateOutput` | `GameStateProvider` |
| Lookup | GET | `/games/lookup` | — | `GameLookupOutput` | `GameLookupProvider` |
| Join | POST | `/games/{id}/join` | `JoinGameInput` | `JoinGameOutput` | `JoinGameProcessor` |
| Play Card | POST | `/games/{id}/play-card` | `PlayCardInput` | `GameStateOutput` | `PlayCardProcessor` |
| Select Capture | POST | `/games/{id}/select-capture` | `SelectCaptureInput` | `GameStateOutput` | `SelectCaptureProcessor` |
| Next Round | POST | `/games/{id}/next-round` | — | `GameStateOutput` | `NextRoundProcessor` |
| Heartbeat | POST | `/games/{id}/heartbeat` | — | none (`output: false`) | `HeartbeatProcessor` |
| Leave | POST | `/games/{id}/leave` | — | none (`output: false`) | `LeaveGameProcessor` |

All POST operations on `{id}` routes use `read: false` (processors fetch the Game entity themselves via `$uriVariables['id']`). All `{id}` routes have UUID regex requirement: `[0-9a-f-]{36}`.

### Database Schema

Single `games` table. Players embedded inline (always exactly 2). Card arrays stored as JSONB.

| Column | Type | Notes |
|---|---|---|
| `id` | UUID v7 | Primary key, generated via `Uuid::v7()` |
| `name` | VARCHAR(60) | Nullable, unique partial index (WHERE name IS NOT NULL) |
| `state` | VARCHAR(20) | Enum: waiting, playing, choosing, round-end, game-over, finished |
| `player1_token` | VARCHAR(64) | 64-char hex auth token, nullable |
| `player2_token` | VARCHAR(64) | 64-char hex auth token, nullable |
| `player1_name` | VARCHAR(30) | Player display name, nullable |
| `player2_name` | VARCHAR(30) | Player display name, nullable |
| `player1_hand` | JSONB | Array of card objects `[{suit, value}, ...]` |
| `player2_hand` | JSONB | Array of card objects |
| `table_cards` | JSONB | Cards currently on the table |
| `deck` | JSONB | Remaining draw pile |
| `current_player` | INT | 0 or 1 |
| `dealer_index` | INT | 0 or 1, alternates each round |
| `last_capturer` | INT | Nullable, 0 or 1 |
| `pending_play` | JSONB | Nullable, stores `{card, playerIndex, options}` when in choosing state |
| `player1_captured` | JSONB | All cards captured by player 1 this round |
| `player2_captured` | JSONB | All cards captured by player 2 this round |
| `player1_scope` | INT | Number of scope scored by player 1 this round |
| `player2_scope` | INT | Number of scope scored by player 2 this round |
| `player1_total_score` | INT | Cumulative score across all rounds |
| `player2_total_score` | INT | Cumulative score across all rounds |
| `round_history` | JSONB | Array of `{scores, totals}` per completed round |
| `deck_style` | VARCHAR(20) | Selected deck style, default 'piacentine' |
| `single_player` | BOOLEAN | True for single-player games vs AI |
| `version` | INT | Doctrine `@Version` for optimistic locking |
| `last_heartbeat1` | TIMESTAMP | Last heartbeat from player 1, nullable |
| `last_heartbeat2` | TIMESTAMP | Last heartbeat from player 2, nullable |
| `created_at` | TIMESTAMP | Game creation time, immutable |

Indexes: `idx_game_state` on `state` column. `uniq_game_name` unique partial index on `name` (WHERE NOT NULL).

### Player Authentication

No traditional auth. Random 64-char hex tokens generated on create/join via `PlayerTokenService::generateToken()` (`bin2hex(random_bytes(32))`). Stored in DB and client `localStorage` (`scopa-player-token-{gameId}`). Sent as `X-Player-Token` header on every request.

The leave endpoint additionally accepts the token as a `?token=` query parameter for `navigator.sendBeacon()` compatibility (sendBeacon cannot set custom headers).

### Session Persistence (Browser Reload)

Active game sessions survive page reloads via `localStorage('scopa-active-game')` which stores `{ gameId, playerToken, myIndex }`.

- **`store.setGame()`** writes the active session to localStorage
- **`store.restoreSession()`** reads it back (used by GameScreen on mount and LobbyScreen for redirect)
- **`store.clearSession()`** removes it (called by `$reset()`, game-over, opponent disconnect)
- **`store.$reset()`** clears both Pinia state and the localStorage session

Flow on reload: URL is `/game/:gameId` → GameScreen mounts → `restoreSession()` restores token + myIndex → `api.getState()` fetches current state from server → deal animation plays → Mercure reconnects.

Flow on lobby visit with active game: LobbyScreen mounts → `restoreSession()` finds session → redirects to `/game/:gameId`.

There is **no `beforeunload` leave beacon** — reloading the page does NOT leave the game. Disconnection is detected by the heartbeat timeout (30s). Players leave explicitly via the exit button or back-to-lobby button.

### API Endpoints

All under `/api/` prefix (configured in `config/routes.yaml`). UUID regex requirement on all `{id}` parameters.

| Method | Path | Description |
|---|---|---|
| `POST` | `/games` | Create game (multiplayer or single-player) |
| `POST` | `/games/{id}/join` | Join existing game |
| `GET` | `/games/{id}` | Get game state (requires token) |
| `GET` | `/games/lookup?name=` | Lookup game by name |
| `POST` | `/games/{id}/play-card` | Play a card `{cardIndex}` |
| `POST` | `/games/{id}/select-capture` | Choose capture option `{optionIndex}` |
| `POST` | `/games/{id}/next-round` | Start next round |
| `POST` | `/games/{id}/heartbeat` | Keep-alive |
| `POST` | `/games/{id}/leave` | Leave game (also accepts `?token=`) |

### Mercure (Real-time)

Each player subscribes to SSE topic: `/games/{gameId}/player/{playerIndex}`

Event types published: `game-state`, `turn-result`, `choose-capture`, `round-end`, `game-over`, `opponent-disconnected`.

Sequencing: `turn-result` published first, then `game-state`. Mercure SSE preserves order within a topic. The frontend processes them in sequence: `turn-result` triggers animation, `game-state` is stashed and committed after animation completes.

Publishing uses **Symfony Mercure Bundle's `HubInterface`** (not raw curl). The `MercurePublisher` service injects `HubInterface` and creates `Update` objects with topic, data, and event type. Configuration in `config/packages/mercure.yaml` sets the JWT secret and publish permissions.

JWT auth: Mercure bundle mints HS256 JWTs using the shared secret from `MERCURE_JWT_SECRET` env var. Anonymous mode enabled on Mercure hub for subscribers.

### Game Engine (GameEngine.php)

Key methods: `initializeGame()`, `startRound()`, `dealHands()`, `findCaptures()`, `findSubsetsWithSum()`, `playCard()`, `selectCapture()`, `endRound()`, `nextRound()`, `getStateForPlayer()`.

Capture logic: single-card matches take priority over sum combinations. Subset-sum via recursive backtracking (`findSubsetsWithSum` → `backtrack()`). The `backtrack()` method requires `count($current) >= 2` for sum matches (must be at least 2 table cards).

Race condition prevention: Doctrine `@Version` optimistic locking on the Game entity. Each processor wraps `$entityManager->flush()` in a try/catch for `OptimisticLockException`, throwing `ConflictHttpException` (HTTP 409) on conflict.

### AI (AIService.php)

Evaluates every legal play with multi-factor scoring:
- Capture: +10/card, +15/denari, +100 sette bello, +0.5×primiera, +12/seven, +6/six, +80 scopa, +3/multi-card
- Placement: -0.8×primiera, -120 sette bello, -20 denari, -25 sevens, -30 easy scopa, -15 matching, +5 face cards

Key methods: `evaluateMove(Game, aiIndex)` returns `{cardIndex, optionIndex}`. `autoSelectCapture(Game)` returns the best option index when AI faces a capture choice.

Async via Symfony Messenger: `HandleAITurnMessage` dispatched after player move. Handler (`HandleAITurnHandler`) sleeps 1.5s, then plays. If AI needs to choose capture, it auto-selects immediately. If AI's turn again after re-deal, dispatches another message.

### Game State Structure (GameStateOutput)
```json
{
  "state": "playing|choosing|round-end|game-over",
  "currentPlayer": 0,
  "myIndex": 0,
  "myName": "...",
  "opponentName": "...",
  "myHand": [{"suit":"Denari","value":7}, ...],
  "myCapturedCount": 5,
  "myScope": 1,
  "myTotalScore": 3,
  "opponentHandCount": 2,
  "opponentCapturedCount": 8,
  "opponentScope": 0,
  "opponentTotalScore": 2,
  "table": [{"suit":"Coppe","value":3}, ...],
  "deckCount": 20,
  "isMyTurn": true,
  "pendingChoice": null,
  "roundHistory": [],
  "deckStyle": "piacentine",
  "turnResult": null
}
```

### Input/Output DTO Architecture

All API endpoints use explicit DTOs for request deserialization and response serialization:

**Input DTOs** (Symfony Validator constraints):
- `CreateGameInput`: `playerName` (NotBlank, max 30), `gameName` (nullable, max 60), `singlePlayer` (bool), `deckStyle` (Choice: piacentine/napoletane/toscane/siciliane)
- `JoinGameInput`: `playerName` (NotBlank, max 30)
- `PlayCardInput`: `cardIndex` (NotNull, >=0)
- `SelectCaptureInput`: `optionIndex` (NotNull, >=0)

**Output DTOs** (readonly constructor properties):
- `CreateGameOutput`: `gameId`, `playerToken`, `state`, `gameState` (nullable `GameStateOutput`)
- `JoinGameOutput`: `gameId`, `playerToken`, `gameState`
- `GameStateOutput`: 20 fields (see JSON structure above)
- `GameLookupOutput`: `id`, `name`, `state`

### Provider/Processor Pattern

**State Providers** implement `ApiPlatform\State\ProviderInterface`:
- `GameStateProvider`: Fetches Game by `$uriVariables['id']`, reads `X-Player-Token` from `RequestStack`, resolves player index, returns `GameStateOutput` via `GameEngine::getStateForPlayer()`. Returns 403 on invalid/missing token, 404 on missing game.
- `GameLookupProvider`: Reads `name` query param, queries for matching game with state=waiting, returns array of `GameLookupOutput`.

**State Processors** implement `ApiPlatform\State\ProcessorInterface`:
- Each processor fetches the Game entity from DB using `$uriVariables['id']` + `EntityManagerInterface`
- Authenticates the player via `X-Player-Token` header (using `RequestStack`)
- Calls appropriate `GameEngine` methods
- Publishes Mercure events via `MercurePublisher`
- Dispatches `HandleAITurnMessage` when it's AI's turn in single-player games
- Handles optimistic locking via try/catch on `flush()`

## Frontend Architecture

### State Management (Pinia)

Two-layer state model to prevent animation flicker:

- **`displayState`**: What the template renders. NEVER updated during animations.
- **`serverState`**: Latest state from the server. May be ahead of displayState.
- **`pendingState`**: Buffered state waiting for animation to finish.
- **`commitState(state)`**: Updates both displayState and serverState. Use only when NOT animating.
- **`stashState(state)`**: Updates serverState + pendingState only. Display unchanged.
- **`finishAnimation()`**: Commits pendingState to displayState, sets animating=false.

Additional store state: `gameId`, `playerToken`, `myIndex`, `animating` flag, `pendingTurnResult`, `pendingEvents` (event queue), `dealHiding`/`dealHidingTable` flags.

Session methods: `setGame()` (persists to localStorage), `restoreSession()` (reads from localStorage), `clearSession()` (removes localStorage entry), `$reset()` (clears all state + session).

### Routing

Three routes configured via Vue Router:
- `/` → `LobbyScreen` (create/join/single-player)
- `/waiting/:gameId` → `WaitingScreen` (Mercure subscription, navigate on game start)
- `/game/:gameId` → `GameScreen` (game board + all logic)

### Animation System

**INVARIANT: `store.commitState()` is called ONLY AFTER all animations finish. NO EXCEPTIONS.**

All DOM changes during animation are imperative (`el.remove()`, `el.style`, `document.createElement`, `appendChild`). Vue re-renders via `commitState` only at the very end. This ensures scores, turn indicator, captured decks, deck count, and opponent hand count never update before the visual animation completes.

#### Event Flow
```
Mercure turn-result  → handleTurnResult(data)         [sets animating=true]
Mercure game-state   → stashState(newState)            [buffers, no render]
                     → runPlaceAnimation/runCaptureAnimation
                     → finishAnimation() at end        [Vue re-renders]
                     → sleep(600ms) afterAnimation delay
                     → commit any stashed pendingState  [if arrived during delay]
                     → processQueue()                  [handle queued events]
```

Events that arrive while busy (`store.animating || inPostAnimDelay`) are queued via `store.queueEvent()` and processed after animation via `processQueue()` → `store.shiftEvent()`.

**Event queuing rules:**
- `turn-result`, `round-end`, `game-over`: always queued when busy
- `game-state`: **stashed** if the queue is empty (for the current animation's `finishAnimation`), **queued** if there are already queued events (preserves ordering for multiple turns)
- `canPlay` blocks during BOTH `animating` and `inPostAnimDelay` to prevent plays during the 600ms gap

**processQueue chaining:**
- After `turn-result`: `handleTurnResult` runs animation, which calls `processQueue` at the end
- Before `turn-result` from queue: peeks at next event; if `game-state`, pre-stashes it so `finishAnimation` can commit the correct intermediate state
- After `game-state` (non-deal): commits state, then **chains** to `processQueue` for remaining events
- After `round-end`/`game-over`: shows overlay, chains to `processQueue`
- `runDealAnimation` early return (no deck rect): calls `processQueue` to avoid stuck events

#### Place Animation
1. Hide source card in hand (`visibility: hidden`)
2. Snapshot table + hand positions
3. Imperatively create card element on table (hidden, `opacity: 0`)
4. Imperatively remove played card from hand
5. FLIP existing table + hand cards to new positions
6. Clone animates from hand to table in animation layer (500ms)
7. Reveal real card, remove clone
8. **commitState** — scores, turn indicator, etc. update

#### Capture Animation
1. Hide source card, create clone in animation layer
2. Clone slides to landing card on table (500ms)
3. Pause (150ms), glow captured cards (500ms)
4. Snapshot positions, imperatively remove captured cards + played card
5. FLIP remaining cards
6. Sweep clones to captured deck (450ms, 100ms stagger)
7. **commitState** — captured deck count, scores, etc. update

#### Deal Animation
1. `dealHiding` + `dealHidingTable` flags set to true
2. commitState — Vue renders cards with `opacity: 0` (via `:style` binding)
3. `store.animating = true` — blocks all incoming events
4. Card-back wrappers animate from deck to each card position (350ms, 150ms stagger)
5. Each wrapper arrival reveals the real card (`opacity: 1`)
6. After all cards dealt, clear flags, set `animating = false`
7. Process any pending events that arrived during the deal

#### Animation Timings
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
| afterAnimation delay | 600ms default | — |
| Safety timeout | 10000ms | — |

#### FLIP Utilities (flipUtils.ts)

Helper functions for the animation system:
- `snapshotPositions(container, selector)` — Records DOMRect positions keyed by `data-card-key`
- `animateFLIP(before, after, container, selector, duration, easing)` — Calculates deltas and animates using Web Animations API
- `createCardClone(card, rect, deckStyle)` — Creates a fixed-position DOM element with card face image
- `createCardBackClone(rect, deckStyle)` — Same but for card backs (deal animation)
- `animateClone(clone, from, to, duration, easing, scale?)` — Animates clone between two DOMRects
- `cardKey(card, index?)` — Generates stable key string for FLIP tracking
- `sleep(ms)` — Promise-based delay

### Game Layout (CSS Grid)

```
grid-template-rows: 1fr auto 1fr
```

- **Row 1 (1fr)**: Opponent area — content anchored to bottom (`justify-content: flex-end`)
- **Row 2 (auto)**: Table area — FIXED height (340px desktop, calc(50vh) mobile). Contains a 2×5 CSS grid of fixed card slots. Cards fill slots left→right, row 1 then row 2. One row → centred vertically. Two rows → also centred. Place animation targets the next empty slot. After capture, remaining cards reflow into contiguous slots with smooth FLIP animation.
- **Row 3 (1fr)**: My area — content anchored to top (`column-reverse` + `flex-end`)
- **Gap**: 24px desktop, 16px mobile (clearance for card hover animation)

### Key CSS Details

| Element | Desktop | Mobile (<600px) |
|---|---|---|
| Card | 75 × 133px | 58 × 103px |
| Card hover lift | translateY(-14px) | translateY(-14px) |
| Table area height | 340px (fixed) | calc(50vh - 8px), min 200px, max 380px |
| Table cards height | 276px (2 rows) | flex, overflow hidden |
| Grid gap | 24px | 16px |
| Captured stack | 75 × 133px | 40 × 71px |
| Deck visual | 75 × 133px (left: 12px) | 40 × 71px (left: 8px) |
| Deck padding-left | 70px | 50px |
| Max game width | 900px | 100vw |

### Z-Index Layers
| z-index | Element |
|---|---|
| 2 | Captured deck, deck visual |
| 50 | Animation layer |
| 51-52 | Animation clones |
| 100 | Overlays |
| 200 | Scopa flash |
| 300 | Confetti canvas |
| 500 | Disconnect banner |

### Composables

**useApi.ts**: REST client wrapping `fetch()`. Automatically includes `X-Player-Token` header from Pinia store. Methods for all 9 endpoints. Validates `Content-Type` header before parsing JSON. Throws on 409 (conflict), 403 (access denied), and other errors.

**useMercure.ts**: Creates `EventSource` connection to `/.well-known/mercure?topic=...`. Accepts typed handler callbacks for each event type. Auto-disconnects on component unmount via `onUnmounted`.

**useDeckStyle.ts**: Reactive `ref<DeckStyle>` persisted to `localStorage('scopa-deck-style')`. Global singleton (defined at module level, not per-component).

### Internationalization (i18n)

Lightweight, custom i18n system in `frontend/src/i18n/` — no external library.

- **Supported locales**: Italian (`it`), English (`en`)
- **Composable**: `useI18n()` returns `{ locale, setLocale, t }`. Module-level singleton `ref<Locale>` so all components share the same reactive locale.
- **Translation files**: `it.ts` (source of truth for keys) and `en.ts`. Both export `as const satisfies Record<string, string>` for type safety. `TranslationKey` type derived from the Italian file.
- **`t(key, params?)`**: Looks up key in current locale, falls back to English, then returns raw key. Supports `{param}` interpolation via `replaceAll`.
- **Language detection**: On first visit, checks `navigator.language` — Italian browsers get `it`, all others get `en`. Persisted to `localStorage('scopa-locale')`.
- **Language selector**: Two buttons on the lobby screen (🇮🇹 Italiano / 🇬🇧 English). Styled with `.language-selector` / `.lang-btn` classes matching the deck selector aesthetic.
- **Coverage**: All user-facing strings in all Vue components, overlays, effects, and API error messages use `t()` keys. No hardcoded display text remains in templates.

### Docker Architecture

```
nginx (port 5982)
  ├── /              → Vue SPA (built in nginx Dockerfile multi-stage)
  ├── /assets/       → Card images (copied into nginx image from frontend build)
  ├── /api/          → fastcgi_pass php:9000
  └── /.well-known/mercure → proxy to mercure:80 (SSE, no buffering)

php (port 9000)     → PHP-FPM (FastCGI)
messenger-worker    → Same image, runs `messenger:consume async --time-limit=3600`
cron                → Same image, runs crond with scheduled Symfony console commands
postgres            → Game state persistence (with healthcheck)
mercure             → SSE hub (anonymous mode, CORS *)
```

Nginx Dockerfile is multi-stage: builds Vue frontend with Node 22, then copies dist into nginx 1.27 image along with config. Card assets are part of the frontend build output.

Docker Compose uses health checks and `depends_on` conditions to ensure proper startup order: postgres → php → messenger-worker + cron + mercure → nginx.

#### Cron Container

The `cron` service is a dedicated container for all recurring background tasks. It reuses the PHP API image and runs `crond` in the foreground. Crontab entries are installed at container startup via the `command` block.

**Adding a new scheduled task**: Add a new crontab line in the `cron` service's command block in `docker-compose.yml`. All output should redirect to `/proc/1/fd/1` so it appears in `docker compose logs cron`.

Current cron jobs:
| Schedule | Command | Purpose |
|---|---|---|
| `* * * * *` (every minute) | `app:cleanup-games` | Delete games inactive for >10 minutes |

## Hard Constraints

These constraints MUST be respected after EVERY change. Verify all of them before committing.

### Layout Constraints
1. **Playing area NEVER changes size** — fixed height (340px desktop, calc(50vh) mobile), `overflow: hidden`
2. **Playing area NEVER moves** — CSS grid `auto` row, position depends only on viewport height
3. **Playing area NEVER shrinks when deck empties** — deck is `position: absolute`, fades to `opacity: 0`, table-center `padding-left` transitions smoothly
4. **Turn indicator NEVER overlapped and NEVER causes layout shift** — fixed `height: 24px` slot in player-area sub-grid, toggled via `visibility:hidden` (not `v-if`), always occupies space
5. **Player names NEVER move** — fixed `height: 28px`, in a dedicated grid row within `.player-area` sub-grid, position depends only on viewport height
6. **Captured decks NEVER shift** — pinned in fixed grid column within `.hand-strip` (3-column grid: `75px 1fr 75px`). Mine at column 3 (right of hand), opponent at column 1 (left of hand). Hand cards centred in column 2.
7. **Hand-to-table gap** — 24px desktop, 16px mobile (clearance for 14px card hover lift + padding)
8. **Card hover NOT clipped** — no `overflow: hidden` on `.player-area`
8b. **Table cards in fixed 2×5 grid** — `.table-center` is `display: grid; grid-template-columns: repeat(5, 75px); grid-template-rows: repeat(2, 133px)`. Cards fill contiguous slots. ≤5 cards → one row centred; >5 → two rows centred. Place animation targets the next empty slot. After capture, remaining cards reflow into contiguous slots via FLIP.

### Animation Constraints
9. **State committed ONLY after animation ends** — `store.commitState()`/`store.finishAnimation()` called at the very end of every animation function. NO EXCEPTIONS. All mid-animation DOM changes are imperative.
10. **Captured deck updates ONLY after sweep** — cards hidden with visibility:hidden during sweep, state committed after sweep clones reach captured deck
11. **Card animation visible full path** — all moving cards use clones in `#animation-layer` (z-index 50, position fixed), never animated inside `overflow: hidden` table-area
12. **Deal animation blocks events** — `store.animating = true` during deal, incoming events stashed and processed after deal completes
13. **No visual flash on deal** — `dealHiding`/`dealHidingTable` flags render cards with `opacity: 0`, revealed one-by-one by animation
14. **Vue-managed DOM NEVER modified by animation code** — no `appendChild`/`remove` on `.table-center` or `.hand-row`. Only `visibility:hidden` via tracked `setStyle()`. All tracked styles restored via `restoreStyles()` BEFORE commitState.
15. **No stale clones after animation** — `clearLayer()` called after every animation, before commitState
16. **Post-animation delay blocks events** — `inPostAnimDelay` flag keeps events queued during the 600ms post-animation gap, preventing visual jumps
17. **Deal animation on every entry** — deal animation runs on first game load, re-deal mid-round, and new round (not just Mercure events)
18. **GPU-composited motion** — `flyTo()` uses `transform: translate() scale()` instead of animating `left`/`top` for smooth 60fps
19. **FLIP rearrangement on commitState** — after commitState, surviving table cards and hand cards animate smoothly from old positions to new via `snapshotByIdentity()`→`flipRearrange()`. Uses card identity (`value-suit`) not index, so cards that shift indices still animate correctly.
20. **Deck visual NEVER overlaps table cards** — on mobile, deck visual is 40×71px at `left: 8px` (extends to 48px), table grid `padding-left: 50px` ensures 2px clearance. On desktop, deck is 75×133px at `left: 12px` (extends to 87px), `padding-left: 70px` ensures clearance with the deck's `position: absolute` keeping it out of flow.
21. **Sweep animation shrinks to EXACT captured deck size** — `flyTo()` scale is relative to the source card (`scale * fromW`), NOT the target rect. On mobile: cards (58px) shrink to captured deck (40px) via `scale ≈ 0.689`. On desktop: no scale needed (both 75px). The final visual size MUST match the captured deck dimensions exactly.
22. **Deal animation scales clones to match target card size** — when deck visual is smaller than card slots (mobile: 40px deck → 58px cards), deal clones grow via `dealScale = targetW / deckW`. On desktop (same size), no scale is applied. Clone MUST arrive at the exact size of the target card slot.
23. **Desktop dimensions NEVER affected by mobile fixes** — all mobile-specific sizing is inside `@media (max-width: 600px)` blocks. Animation scale calculations use runtime DOM measurements, so they are automatically correct on both breakpoints. Any change to mobile dimensions MUST verify desktop is unchanged.

## Security

- Player tokens: 64-char hex (`bin2hex(random_bytes(32))`) via `PlayerTokenService`
- Input sanitization: trim, 30-char max, control character stripping via `PlayerTokenService::sanitizeName()`
- Input validation: Symfony Validator constraints on all Input DTOs (NotBlank, Length, Choice, GreaterThanOrEqual)
- HTML escaping: Vue `{{ }}` auto-escapes, ScoreTable uses manual `esc()` function for v-html safety
- Route requirements: UUID regex `[0-9a-f-]{36}` on all `{id}` parameters
- Optimistic locking: Doctrine `@Version` column, processors catch `OptimisticLockException` → HTTP 409
- API response validation: `useApi.ts` checks Content-Type before `response.json()`

## Code Review & Testing (Mandatory)

**After every code change, the following review and testing workflow is mandatory:**

1. **Review** — Spawn the appropriate reviewer agent(s) based on what changed:
   - **`frontend-reviewer`** agent — for any frontend (Vue/TypeScript/CSS) changes
   - **`backend-reviewer`** agent — for any backend (PHP/Symfony) changes
   - If both frontend and backend changed, spawn **both** reviewers (they can run in parallel)
2. **Test** — After the reviewer(s) finish, spawn the **`tester`** agent to validate everything

This workflow is **not optional** — it must run after every change, no exceptions.

**HARD RULE: NEVER commit or push until ALL reviewer and tester agents have fully completed.** Wait for every spawned reviewer and tester agent to finish and report results before running `git commit` or `git push`. No exceptions — do not commit early to satisfy hooks or for any other reason.

### Tester Agent

All testing is delegated to the **tester** agent (`.claude/agents/tester.md`). It handles:
- Backend PHPUnit tests (via Docker)
- Frontend build verification
- Layout, stability, and animation invariant checks
- End-to-end test scenarios and visual integrity tests

All test knowledge, checklists, and verification procedures live in the agent definition.

## Known Limitations

- No spectator mode
- No game history persistence beyond current session
- AI difficulty is not configurable (always plays optimally)
- No keyboard navigation or screen reader support

## Development Notes

- The game is entirely server-authoritative — all game logic runs on the server
- The UI supports Italian and English (see i18n section), with language selectable on the lobby screen
- **No Symfony controllers are used** — all endpoints use API Platform State Providers and Processors
- CSS files use the same visual design as the lobby/game screens, adapted for CSS grid layout
- Heartbeat system: frontend sends every 10s, backend checks opponent staleness (>30s = disconnected)
- Browser reload resumes game (session persisted in localStorage, no beforeunload leave beacon)
- Exit button (×) in top-right corner of game board explicitly leaves the game
