# Italian Card Games (Scopa, Briscola & Tressette)

## Overview

Web-based two-player Italian card games with real-time multiplayer and single-player AI mode. Supports **Scopa** (table-capture), **Briscola** (trick-taking with trump), and **Tressette in Due** (trick-taking with follow-suit). Uses traditional Italian regional card images on a green casino-table background. Built with API Platform (PHP/Symfony), Vue 3 + TypeScript, PostgreSQL, and Mercure SSE for real-time. The architecture supports adding new card games cleanly via the strategy pattern.

## Tech Stack

- **Backend**: PHP 8.5 + Symfony 8.0 + API Platform 4.3
- **Frontend**: Vue 3.5 + TypeScript 6 + Pinia 3 + Vue Router 5 (SPA)
- **Database**: PostgreSQL 18 via Doctrine ORM 3.6 (JSONB for card arrays)
- **Real-time**: Mercure SSE (server→client push), REST API (client→server)
- **AI**: Symfony Messenger async handler with 1.5s delay
- **Containerization**: Docker Compose — 7 services, Node 24, nginx 1.29, HTTPS on configurable port (default 5982)
- **Card assets**: 4 deck styles, each with 40 card face images + card back

## How to Run

```bash
cp .env.dist .env           # Create local config (edit as needed)
docker compose up --build   # Starts on https://localhost:5982
```

See **[Deployment Guide](docs/deployment.md)** for production setup with custom TLS certificates, NAT port forwarding, and HTTP/3 configuration.

Services: postgres, php (API), messenger-worker (AI), cron (cleanup), mercure (SSE), nginx (SPA + reverse proxy), acme (Let's Encrypt via Dynu DNS, optional profile), node (frontend dev tools, `dev` profile).

**IMPORTANT: Always use Docker Compose to run all tooling, tests, builds, and commands.** No Node.js, PHP, or Composer is installed on the host machine. All commands must run inside containers.

```bash
# Backend PHPUnit tests
docker compose exec php bin/phpunit

# Frontend tests (vitest) — uses persistent node container with cached node_modules
make test-front

# Frontend build check
make build-front

# Run only frontend tests for changed files
make test-front-changed

# First-time setup / reinstall node_modules
make node-install

# Symfony console commands
docker compose exec php bin/console <command>
```

## Project Structure

```
scopa/
  Makefile                     # Shortcuts: up, down, build, logs, test, shell, db, acme-up/down
  docker-compose.yml           # 7 services, configurable port
  ssl/                         # TLS certificates: cert.pem + key.pem (gitignored)
  .env.dist                    # Deploy config template (hostname, port, TLS mode, Dynu DNS)
  .env                         # Local deploy config (gitignored)
  
  api/                         # API Platform (PHP/Symfony)
    Dockerfile                 # PHP 8.5 FPM + composer + entrypoint
    entrypoint.sh              # cache:clear, migrations, messenger:setup-transports
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
      Version20260405000001.php  # games table (initial Scopa schema)
      Version20260408000001.php  # Add multi-game: game_type, briscola_card, last_trick, trick_leader
    src/
      Kernel.php
      Entity/
        Game.php               # Single entity: all game state in one table + ApiResource operations
      Enum/
        GameType.php           # scopa|briscola|tressette
        GameState.php          # waiting|playing|choosing|round-end|game-over|finished
        DeckStyle.php          # piacentine|napoletane|toscane|siciliane
        Suit.php               # Denari|Coppe|Bastoni|Spade (with letter() method)
      Dto/
        Input/
          CreateGameInput.php  # playerName, gameName?, singlePlayer, deckStyle, gameType
          JoinGameInput.php    # playerName
          PlayCardInput.php    # cardIndex
          SelectCaptureInput.php # optionIndex
        Output/
          CreateGameOutput.php # gameId, playerToken, state, gameType, gameState?, mercureToken?
          JoinGameOutput.php   # gameId, playerToken, gameState, mercureToken?
          GameStateOutput.php  # Full player-specific game state (24 fields, incl. mercureToken, gameType, briscolaCard, lastTrick)
          GameLookupOutput.php # id, name, state, gameType
      Service/
        GameEngine.php         # Interface: initializeGame, startGame, playCard, getStateForPlayer, selectCapture, nextRound
        GameEngineFactory.php  # Resolves GameEngine implementation by GameType
        ScopaEngine.php        # Scopa: table-capture logic, multi-round
        BriscolaEngine.php     # Briscola: trick-taking logic, single game, trump suit
        TressetteEngine.php    # Tressette: trick-taking, always follow-suit, visible draws
        DeckService.php        # Deck creation, Fisher-Yates shuffle
        AIService.php          # Interface: evaluateMove, autoSelectCapture
        AIServiceFactory.php   # Resolves AIService implementation by GameType
        ScopaAIService.php     # Scopa AI: multi-factor capture/placement scoring
        BriscolaAIService.php  # Briscola AI: trump management, trick evaluation
        TressetteAIService.php # Tressette AI: suit-control lead/follow strategy with drawn card tracking
        ScopaScoringService.php # Scopa 5-category round scoring
        BriscolaScoringService.php # Briscola card point values and strength ranking
        TressetteScoringService.php # Tressette ×3 integer point values and strength ranking
        MercurePublisher.php   # Publishes SSE events via Symfony Mercure HubInterface
        MercureTokenService.php # Generates Mercure subscriber JWT tokens
        PlayerTokenService.php # Token generation, name sanitization
        PlayerAuthenticator.php # Game loading + player token validation
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
      EventListener/
        MercureTerminateListener.php # Deferred Mercure publishing via kernel events
      ValueObject/
        Card.php               # Card value object (suit + value)
        CardCollection.php     # Typed card list
        AIMove.php             # AI move evaluation result
        PendingPlay.php        # Pending capture choice state
        LastTrick.php          # Last resolved trick (Briscola)
        TurnResult.php         # Turn outcome data
        TurnResultType.php     # Turn result type enum
        RoundScores.php        # Round scoring breakdown
        RoundHistoryEntry.php  # Round history record
        ScoreRow.php           # Score table row
        SweepData.php          # Scopa sweep data
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
          BriscolaEngineTest.php
          TressetteEngineTest.php
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
      apple-touch-icon.png
      assets/cards/            # Card images (4 deck styles)
        piacentine/            # 40 files + bg
        napoletane/            # 40 files + bg
        toscane/               # 40 files + bg
        siciliane/             # 40 files + bg
    src/
      main.ts                  # Vue app + Pinia + Router setup
      vite-env.d.ts            # TypeScript declarations for .vue modules
      App.vue                  # Root component (router-view)
      types/
        card.ts                # Card, DeckStyle, SUIT_LETTER, DECK_EXT, cardImagePath, cardBackPath, PRIMIERA_VALUES, BRISCOLA_CARD_POINTS
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
        flipUtils.ts           # FLIP helpers: snapshotPositions, animateFLIP, createCardClone, createCardBackClone, animateClone, computeSlotRect, computeFlyToDelta, sleep, cardKey
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
          BriscolaScoreTable.vue    # Briscola-specific score display
          TressetteScoreTable.vue   # Tressette score display with ultima bonus
          ScoreDetailDialog.vue     # Detailed card breakdown modal
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
    default.conf.template      # Nginx config template (envsubst: EXTERNAL_HOSTNAME, EXTERNAL_PORT)
    fastcgi_api.conf           # FastCGI params for PHP-FPM
    entrypoint.sh              # TLS cert management (provided or self-signed fallback)
```

## Game Rules

This application supports multiple Italian card games. Full rules for each game are in dedicated files:

- **[Scopa Rules](docs/scopa-rules.md)** — Table-capture game, multi-round to 11 points
- **[Briscola Rules](docs/briscola-rules.md)** — Trick-taking game with trump suit, single game to 61+ points
- **[Tressette Rules](docs/tressette-rules.md)** — Trick-taking game with always follow-suit, visible draws, 35 total points

### Shared Card Deck
- **40 cards** in 4 suits: **Denari** (coins), **Coppe** (cups), **Bastoni** (clubs), **Spade** (swords)
- Values 1-10 per suit (8=Fante/Jack, 9=Cavallo/Knight, 10=Re/King)
- Card image naming: `{value}{suit_letter}.{ext}` where suit letters are: `d`=Denari, `c`=Coppe, `b`=Bastoni, `s`=Spade

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
| `game_type` | VARCHAR(20) | Enum: scopa, briscola (default 'scopa') |
| `briscola_card` | JSONB | Nullable, the revealed trump card `{suit, value}` (Briscola only) |
| `last_trick` | JSONB | Nullable, last resolved trick `{leaderCard, followerCard, winnerIndex}` (Briscola only) |
| `trick_leader` | INT | Nullable, who leads the current trick (Briscola only) |
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

JWT auth: `MercureTokenService` generates HS256 subscriber JWTs scoped to specific game/player topics. Publishing JWTs are minted by Mercure bundle using the shared secret from `MERCURE_JWT_SECRET` env var. Anonymous mode enabled on Mercure hub for subscribers.

Deferred publishing: `MercureTerminateListener` defers Mercure publishes to the kernel `TerminateEvent` (after the response is sent), reducing request latency.

### Multi-Game Architecture (Strategy Pattern)

The backend uses a **strategy pattern** to support multiple card games:

- **`GameEngine`** (interface): Defines the contract — `initializeGame()`, `startGame()`, `playCard()`, `getStateForPlayer()`, `selectCapture()`, `nextRound()`
- **`GameEngineFactory`**: Resolves `ScopaEngine`, `BriscolaEngine`, or `TressetteEngine` based on `Game::getGameType()`
- **`AIService`** (interface): Defines `evaluateMove()` and `autoSelectCapture()`
- **`AIServiceFactory`**: Resolves `ScopaAIService`, `BriscolaAIService`, or `TressetteAIService` based on game type

All processors and providers inject `GameEngineFactory` (not a specific engine). The factory resolves the correct engine from `$game->getGameType()`.

**Adding a new game**: Create `NewGameEngine implements GameEngine`, `NewGameAIService implements AIService`, `NewGameScoringService`, add enum case to `GameType`, register in both factories.

Race condition prevention: Doctrine `@Version` optimistic locking on the Game entity. Each processor wraps `$entityManager->flush()` in a try/catch for `OptimisticLockException`, throwing `ConflictHttpException` (HTTP 409) on conflict.

### Scopa Engine (ScopaEngine.php)

Key methods: `initializeGame()`, `startGame()`, `dealHands()`, `findCaptures()`, `playCard()`, `selectCapture()`, `nextRound()`, `getStateForPlayer()`.

Capture logic: single-card matches take priority over sum combinations. Subset-sum via recursive backtracking. Multi-round game to 11 points. See `docs/scopa-rules.md` for full rules.

### Briscola Engine (BriscolaEngine.php)

Key methods: `initializeGame()`, `startGame()`, `playCard()`, `getStateForPlayer()`.

Trick-taking logic: leader plays to table, follower plays to resolve trick. Trump suit determined by briscola card. 20 tricks per game, 120 total points. Winner draws first after each trick. See `docs/briscola-rules.md` for full rules.

**Does NOT support** `selectCapture()` or `nextRound()` — throws `\LogicException` (state guards prevent these from being called).

### Tressette Engine (TressetteEngine.php)

Key methods: `initializeGame()`, `startGame()`, `playCard()`, `getStateForPlayer()`.

Trick-taking game (Tressette in due a metà mazzo). 10 cards dealt each, always must follow suit, draw after each trick (drawn cards visible to opponent). No trump suit — different suits always lose to leader. Card strength: 3>2>Asso>Re>Cavallo>Fante>7>6>5>4. Points (×3 integer): Asso=3, 2/3/figures=1, rest=0. Ultima bonus: +3 for last trick winner. Total: 35 points.

**Does NOT support** `selectCapture()` or `nextRound()` — throws `\LogicException`.

### AI

**Scopa AI** (`ScopaAIService`): Multi-factor scoring for captures and placements. Weights for card value, denari suit, sette bello, primiera, scopa potential.

**Briscola AI** (`BriscolaAIService`): Separate strategies for leading vs following. Manages trump cards, avoids giving away high-point cards, captures valuable tricks.

**Tressette AI** (`TressetteAIService`): Suit-control strategy with always-follow-suit enforcement. Lead low, prefer long suits for control. Follow with minimum strength to win, discard cheap when losing. Tracks drawn cards (visible to both players). No trump management needed.

Async via Symfony Messenger: `HandleAITurnMessage` dispatched after player move. Handler (`HandleAITurnHandler`) uses factories to resolve the correct engine and AI service. Sleeps 1.5s, then plays.

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
  "turnResult": null,
  "mercureToken": "...",
  "gameType": "scopa",
  "briscolaCard": null,
  "lastTrick": null
}
```

### Input/Output DTO Architecture

All API endpoints use explicit DTOs for request deserialization and response serialization:

**Input DTOs** (Symfony Validator constraints):
- `CreateGameInput`: `playerName` (NotBlank, max 30), `gameName` (nullable, max 60), `singlePlayer` (bool), `deckStyle` (Choice: piacentine/napoletane/toscane/siciliane), `gameType` (enum: scopa/briscola, default scopa)
- `JoinGameInput`: `playerName` (NotBlank, max 30)
- `PlayCardInput`: `cardIndex` (NotNull, >=0)
- `SelectCaptureInput`: `optionIndex` (NotNull, >=0)

**Output DTOs** (readonly constructor properties):
- `CreateGameOutput`: `gameId`, `playerToken`, `state`, `gameType`, `gameState?`, `mercureToken?`
- `JoinGameOutput`: `gameId`, `playerToken`, `gameState`, `mercureToken?`
- `GameStateOutput`: 24 fields (see JSON structure above)
- `GameLookupOutput`: `id`, `name`, `state`, `gameType`

### Provider/Processor Pattern

**State Providers** implement `ApiPlatform\State\ProviderInterface`:
- `GameStateProvider`: Fetches Game by `$uriVariables['id']`, reads `X-Player-Token` from `RequestStack`, resolves player index, returns `GameStateOutput` via `GameEngine::getStateForPlayer()`. Returns 403 on invalid/missing token, 404 on missing game.
- `GameLookupProvider`: Reads `name` query param, queries for matching game with state=waiting, returns array of `GameLookupOutput`.

**State Processors** implement `ApiPlatform\State\ProcessorInterface`:
- Each processor uses `PlayerAuthenticator` to load the Game entity and authenticate the player via `X-Player-Token` header
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

Additional store state: `gameId`, `playerToken`, `myIndex`, `animating` flag, `pendingTurnResult`, `pendingEvents` (event queue), `dealHiding`/`dealHidingTable`/`dealHidingBriscola` flags.

Session methods: `setGame()` (persists to localStorage), `restoreSession()` (reads from localStorage), `clearSession()` (removes localStorage entry), `$reset()` (clears all state + session).

### Routing

Three routes configured via Vue Router:
- `/` → `LobbyScreen` (create/join/single-player)
- `/waiting/:gameId` → `WaitingScreen` (Mercure subscription, navigate on game start)
- `/game/:gameId` → `GameScreen` (game board + all logic)

### Animation System

Full animation documentation: **[Animation System](docs/animation-system.md)** — Event flow, place/capture/deal animations, timings, FLIP utilities, and the critical invariants around `commitState` and hiding flags.

### Communication Layer

Full analysis: **[Communication Layer](docs/communication-layer.md)** — REST API client, Mercure SSE, three-layer state model, event processing pipeline, and known failure modes/hang scenarios.

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
nginx (HTTPS, HTTP/2, HTTP/3 on configurable port)
  ├── :443/tcp         → HTTPS + HTTP/2
  ├── :443/udp         → HTTP/3 (QUIC)
  ├── /                → Vue SPA (built in nginx Dockerfile multi-stage)
  ├── /assets/         → Card images (copied into nginx image from frontend build)
  ├── /api/            → fastcgi_pass php:9000
  └── /.well-known/mercure → proxy to mercure:80 (SSE, no buffering)

php (port 9000)      → PHP-FPM (FastCGI)
messenger-worker     → Same image, runs `messenger:consume async --time-limit=3600`
cron                 → Same image, runs crond with scheduled Symfony console commands
postgres             → Game state persistence (with healthcheck)
mercure              → SSE hub (anonymous mode, CORS *)
acme (optional)      → Let's Encrypt cert via acme.sh + Dynu DNS (profile: letsencrypt)
```

Nginx Dockerfile is multi-stage: builds Vue frontend with Node 24, then copies dist into nginx 1.29 image along with config template. The config (`default.conf.template`) is processed by `envsubst` at container startup — only `EXTERNAL_HOSTNAME` and `EXTERNAL_PORT` are substituted (filtered by `NGINX_ENVSUBST_FILTER=^EXTERNAL_`). TLS certificates are loaded from the `ssl/` bind mount (user-provided, acme.sh-managed, or auto-generated self-signed). Card assets are part of the frontend build output.

Full deployment instructions: **[Deployment Guide](docs/deployment.md)**

Docker Compose uses health checks and `depends_on` conditions to ensure proper startup order: postgres → php → messenger-worker + cron + mercure → nginx.

#### Cron Container

The `cron` service is a dedicated container for all recurring background tasks. It reuses the PHP API image and runs `crond` in the foreground. Crontab entries are installed at container startup via the `command` block.

**Adding a new scheduled task**: Add a new crontab line in the `cron` service's command block in `docker-compose.yml`. All output should redirect to `/proc/1/fd/1` so it appears in `docker compose logs cron`.

Current cron jobs:
| Schedule | Command | Purpose |
|---|---|---|
| `* * * * *` (every minute) | `app:cleanup-games` | Delete games inactive for >10 minutes |

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
- **Architectural documentation must always be updated with every code change.** When modifying code that is described in the `docs/` files (animation system, communication layer, game rules, deployment, etc.) or in this CLAUDE.md, the corresponding documentation MUST be updated in the same commit. Outdated documentation is a bug.
