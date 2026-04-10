# Tressette in Due — Game Rules

## Overview

Tressette in Due is the two-player variant of Tressette, one of Italy's most classic card games. It is a trick-taking game played in two phases: a **stock phase** (where players draw after each trick) and a **hand phase** (where players must follow suit). Unlike Briscola, there is **no trump suit** — winning depends on playing the led suit with higher-ranked cards.

## The Deck

- Same **40-card** Italian deck: 4 suits (Denari, Coppe, Bastoni, Spade), values 1-10
- Face cards: 8=Fante/Jack, 9=Cavallo/Knight, 10=Re/King
- Same card assets and deck styles as Scopa and Briscola

## Initial Deal

1. The deck is shuffled
2. **10 cards** are dealt to each player (20 total)
3. The remaining **20 cards** form the face-down draw pile (stock)
4. No card is revealed — there is **no trump suit**
5. The **non-dealer leads first**

## Card Ranking and Point Values

Card ranking in Tressette differs from Briscola — the **3** is the strongest card, followed by the **2**.

### Strength Order (strongest to weakest within a suit)

| Rank | Card (face value) | Italian Name | Point Value (×3) |
|------|-------------------|-------------|-------------------|
| 1st (strongest) | 3 | Tre | **1** |
| 2nd | 2 | Due | **1** |
| 3rd | 1 (Ace) | Asso | **3** |
| 4th | 10 | Re (King) | **1** |
| 5th | 9 | Cavallo (Knight) | **1** |
| 6th | 8 | Fante (Jack) | **1** |
| 7th | 7 | Sette | **0** |
| 8th | 6 | Sei | **0** |
| 9th | 5 | Cinque | **0** |
| 10th (weakest) | 4 | Quattro | **0** |

### Strength Values for Implementation

| Face Value | Strength (higher wins) |
|------------|----------------------|
| 3 | 10 |
| 2 | 9 |
| 1 (Ace) | 8 |
| 10 (King) | 7 |
| 9 (Knight) | 6 |
| 8 (Jack) | 5 |
| 7 | 4 |
| 6 | 3 |
| 5 | 2 |
| 4 | 1 |

### Point System (Integer Thirds)

Traditional Tressette counts points in fractions of thirds. This implementation multiplies by 3 for integer arithmetic:

| Card | Traditional | ×3 (Implementation) |
|------|------------|---------------------|
| Asso (Ace) | 1 point | **3** |
| 2, 3, Re, Cavallo, Fante | ⅓ point each | **1** each |
| 4, 5, 6, 7 | 0 | **0** |

### Point Distribution

- Points per suit: 3 (Ace) + 1+1+1+1+1 (2, 3, King, Knight, Jack) = **8**
- Total card points: 4 suits × 8 = **32**
- **Ultima bonus**: winning the last trick = **+3 points**
- Grand total: **35** points per game

## Two-Phase Game Play

### Phase 1 — Stock Phase (Tricks 1–10)

During the stock phase, the draw pile has cards remaining.

1. Leader plays any card face-up
2. Follower plays any card — **no obligation to follow suit**
3. Trick resolution (see below)
4. Winner collects both cards into their won-tricks pile
5. **Winner draws first** from stock, then loser draws
6. Winner leads next trick
7. Phase 1 ends when the stock is exhausted (after 10 tricks)

### Phase 2 — Hand Phase (Tricks 11–20)

After the stock is empty, each player has exactly 10 cards.

1. Leader plays any card face-up
2. Follower **must follow suit** if they have a card of the led suit
3. If the follower has no cards of the led suit, they may play any card
4. Trick resolution (see below)
5. Winner collects both cards into their won-tricks pile
6. **No drawing** — stock is empty
7. Winner leads next trick
8. Game ends when all cards are played (after trick 20)

## Trick Resolution

| Scenario | Winner |
|----------|--------|
| Both cards same suit | Higher-ranked card wins |
| Different suits | **Leader's card wins** (always) |

**Key rule**: There is no trump suit. When the follower plays a different suit, the leader **always wins** — even if the follower's card has higher rank or point value. The only way to beat the leader is to play a higher card of the **same suit**.

## Scoring

After all 20 tricks:

1. Each player counts card point values in their captured pile
2. The winner of the **last trick** receives the **ultima bonus** (+3 points)
3. Total available: 32 card points + 3 ultima = **35 points**
4. **18+ points** = win (majority of 35)
5. **17-18** = possible draw (17.5 each is not reachable with integers — one player always has more)

Since 35 is odd, there is always a winner.

## Game States

| State | Description |
|---|---|
| `waiting` | Waiting for second player to join |
| `playing` | Active play — current player must play a card |
| `game-over` | Game finished (all 20 tricks played), showing results |
| `finished` | Game cleaned up after game-over |

Note: Tressette does NOT use `choosing` (no capture choices), `round-end` (single game), or trump-related states.

## Key Differences from Briscola and Scopa

| Aspect | Scopa | Briscola | Tressette |
|--------|-------|----------|-----------|
| Game type | Table capture | Trick-taking | Trick-taking |
| Hand size | 3 (redeal) | 3 (draw 1) | **10** (draw 1 in phase 1) |
| Trump suit | None | Yes (briscola card) | **None** |
| Must follow suit | N/A | No | **Phase 2 only** |
| Card strength | Face value | Ace > 3 > King... | **3 > 2 > Ace > King...** |
| Point system | 5 categories | Simple count (120 total) | **Thirds ×3 (35 total)** |
| Win threshold | First to 11 | 61+ of 120 | **18+ of 35** |
| Special bonuses | Scopa (sweep) | None | **Ultima** (last trick) |
| Visible info | Table cards | Briscola card | **Nothing** (no trump card) |
| Draw pile | Redeal when empty | 33 cards + briscola | **20 cards** |
| Phases | Single | Single | **Two** (stock + hand) |

## Tressette-Specific UI Elements

- **10-card hand**: Cards overlap slightly in a fan layout to fit the screen
- **Trick area**: Central area where the two played cards appear during a trick (like Briscola)
- **Draw pile**: Displayed to the left of the table area (like Briscola, but no revealed card)
- **Won-tricks pile**: Each player's pile shows total points captured
- **Follow-suit indicator**: In phase 2, only playable cards are highlighted when the leader has played
- **No table cards grid**: Like Briscola, there is no persistent table card area

## AI Strategy (Tressette)

Key considerations for AI:

### Phase 1 (Stock — no suit obligation)
- **Low-value leads**: Lead with zero-point cards (4-7) to avoid giving away points
- **Opportunistic captures**: Follow with same-suit higher cards when the trick has point value
- **Asso conservation**: Save Aces for phase 2 when suit control matters more
- **Information gathering**: Track which suits the opponent doesn't follow to

### Phase 2 (Hand — must follow suit)
- **Suit control**: Lead with suits where AI has the strongest remaining cards
- **Forced responses**: Lead suits the opponent may be void in (they must discard)
- **Point protection**: When following with a losing card, play the cheapest
- **Endgame counting**: With fewer cards, deduce opponent's hand and play optimally
- **Ultima**: Consider playing for the last trick bonus when it matters for the outcome
