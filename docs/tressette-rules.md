# Tressette in Due a Metà Mazzo — Game Rules

## Overview

Tressette in Due a Metà Mazzo is a two-player variant of Tressette, one of Italy's most classic card games. It is a trick-taking game where players **always follow suit** when possible. After each trick, both players draw a card from the stock — and **drawn cards are revealed to the opponent**. Unlike Briscola, there is **no trump suit** — winning depends on playing the led suit with higher-ranked cards.

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

## Game Play

### Trick Flow

1. Leader plays any card face-up
2. Follower **must follow suit** if they have a card of the led suit
3. If the follower has no cards of the led suit, they may play any card
4. Trick resolution (see below)
5. Winner collects both cards into their won-tricks pile
6. Both players **draw one card** from the stock (winner draws first) — **drawn cards are shown face-up to the opponent**
7. Winner leads next trick
8. When the stock is exhausted, play continues without drawing
9. Game ends when all cards are played (after trick 20)

### Drawn Cards Are Visible

A key feature of Tressette in Due a Metà Mazzo: when a player draws a card from the stock after a trick, **the card is revealed to the opponent**. Both players can see what each other drew. This adds a memory and information-tracking dimension to the game — skilled players will remember which cards the opponent holds.

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
| Hand size | 3 (redeal) | 3 (draw 1) | **10** (draw 1) |
| Trump suit | None | Yes (briscola card) | **None** |
| Must follow suit | N/A | No | **Always** |
| Card strength | Face value | Ace > 3 > King... | **3 > 2 > Ace > King...** |
| Point system | 5 categories | Simple count (120 total) | **Thirds ×3 (35 total)** |
| Win threshold | First to 11 | 61+ of 120 | **18+ of 35** |
| Special bonuses | Scopa (sweep) | None | **Ultima** (last trick) |
| Drawn cards | N/A | Hidden | **Visible to opponent** |
| Draw pile | Redeal when empty | 33 cards + briscola | **20 cards** |

## Tressette-Specific UI Elements

- **10-card hand**: Cards overlap slightly in a fan layout to fit the screen
- **Trick area**: Central area where the two played cards appear during a trick (like Briscola)
- **Draw pile**: Displayed to the left of the table area (like Briscola, but no revealed card)
- **Won-tricks pile**: Each player's pile shows total points captured
- **Follow-suit indicator**: Only playable cards are highlighted when the leader has played
- **Drawn card reveal**: After each trick, drawn cards fly from the deck and flip face-up before going to the hand, so both players can see what was drawn
- **No table cards grid**: Like Briscola, there is no persistent table card area

## AI Strategy (Tressette)

Key considerations for AI:

### Leading
- **Low-value leads**: Lead with zero-point cards (4-7) to avoid giving away points
- **Suit control**: Lead suits where AI has depth (forces opponent to follow or discard)
- **Strong suit leads**: Leading with strongest card in a long suit controls the game

### Following
- **Win valuable tricks**: Follow with same-suit higher cards when the trick has point value
- **Lose cheaply**: When losing, play zero-point off-suit cards
- **Asso conservation**: Save Aces for situations where suit control matters

### Information Tracking
- **Drawn cards**: AI tracks all drawn cards (visible to both players), knowing which cards the opponent holds
- **Suit voids**: Track which suits the opponent has discarded from to identify voids
- **Endgame counting**: With fewer cards, deduce opponent's hand and play optimally
- **Ultima**: Consider playing for the last trick bonus when it matters for the outcome
