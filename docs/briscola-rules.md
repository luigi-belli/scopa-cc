# Briscola — Game Rules

## Overview

Briscola is a traditional Italian trick-taking card game. Players compete to capture the most card points by winning tricks, with one suit designated as trump (briscola) for the entire game.

## The Deck

- Same **40-card** Italian deck as Scopa: 4 suits (Denari, Coppe, Bastoni, Spade), values 1-10
- Face cards: 8=Fante/Jack, 9=Cavallo/Knight, 10=Re/King
- Same card assets and deck styles as Scopa

## Initial Deal

1. The deck is shuffled
2. **3 cards** are dealt to each player (6 total)
3. The next card is turned **face-up** and placed beside the draw pile — this is the **briscola card**
4. The suit of this card becomes the **trump suit** for the entire game
5. The briscola card is visible to both players at all times
6. The remaining 33 cards form the face-down draw pile
7. The briscola card is the **last card drawn** from the pile

## Card Ranking and Point Values

Cards have a **strength ranking** (for winning tricks) and **point values** (for scoring) that differ from face value.

### Strength Order (strongest to weakest within a suit)

| Rank | Card (face value) | Italian Name | Point Value |
|------|-------------------|-------------|-------------|
| 1st (strongest) | 1 (Ace) | Asso | **11** |
| 2nd | 3 | Tre | **10** |
| 3rd | 10 | Re (King) | **4** |
| 4th | 9 | Cavallo (Knight) | **3** |
| 5th | 8 | Fante (Jack) | **2** |
| 6th | 7 | Sette | **0** |
| 7th | 6 | Sei | **0** |
| 8th | 5 | Cinque | **0** |
| 9th | 4 | Quattro | **0** |
| 10th (weakest) | 2 | Due | **0** |

### Strength Values for Implementation

| Face Value | Strength (higher wins) |
|------------|----------------------|
| 1 (Ace) | 10 |
| 3 | 9 |
| 10 (King) | 8 |
| 9 (Knight) | 7 |
| 8 (Jack) | 6 |
| 7 | 5 |
| 6 | 4 |
| 5 | 3 |
| 4 | 2 |
| 2 | 1 |

### Point Distribution

- Total points in deck: **120** (each suit has 11+10+4+3+2 = 30 points)
- Only 5 card values carry points: Ace (11), Three (10), King (4), Knight (3), Jack (2)
- Cards 2, 4, 5, 6, 7 are worth **0 points** ("lisce" / smooth cards)

## Turn Flow

1. The **non-dealer leads first** (same convention as Scopa)
2. The leader plays one card face-up
3. The other player plays one card face-up — this completes a **trick** (2 cards)
4. **No obligation to follow suit** — a player may play any card from their hand
5. The trick winner collects both cards into their won-tricks pile
6. The trick winner **leads the next trick**
7. Dealer alternates each game

## Trick Resolution

| Scenario | Winner |
|----------|--------|
| Both cards same suit | Higher-ranked card wins |
| Different suits, neither is trump | **Leader's card wins** (first player always wins) |
| Different suits, one is trump | **Trump card wins** (regardless of rank) |
| Both cards are trump | Higher-ranked trump wins |

**Key rule**: When neither card is trump and they are different suits, the first player's card always wins. Playing a non-trump off-suit card is always a losing play.

## Drawing After Tricks

- After each trick, both players draw **one card** from the draw pile (back to 3 cards each)
- **Trick winner draws first**, then the loser
- When only the face-down card + face-up briscola remain: winner takes the face-down card, loser takes the briscola
- After the draw pile is exhausted, the last **3 tricks** are played without drawing

## Game Phases

| Phase | Cards in hand | Draw pile | Notes |
|-------|--------------|-----------|-------|
| Opening deal | 3 each | 33 + 1 briscola | 40 - 6 - 1 = 33 |
| Mid-game (tricks 1-17) | Always 3 after drawing | Decreasing | Draw after each trick |
| End-game (tricks 18-20) | 3 → 2 → 1 | Empty | No drawing, play remaining cards |

Total tricks in a 2-player game: **20** (40 cards / 2 per trick)

## Scoring

- After all 20 tricks, each player counts point values of captured cards
- Total always sums to **120**
- **61+ points** = win
- **60-60** = draw (no winner)

## Winning

- Single game: first to 61+ points wins
- Series: can be played as best-of-3 or best-of-5
- In this implementation: single game per match (like a single round), with option to play again

## Game States

| State | Description |
|---|---|
| `waiting` | Waiting for second player to join |
| `playing` | Active play — current player must play a card |
| `trick-end` | Brief state after trick resolution (for animation) |
| `game-over` | Game finished (all 20 tricks played), showing results |
| `finished` | Game cleaned up after game-over |

Note: Briscola does NOT use `choosing` or `round-end` states (no capture choices, single-game format).

## Key Differences from Scopa

| Aspect | Scopa | Briscola |
|--------|-------|----------|
| Game type | Table capture | Trick-taking |
| Table cards | Visible shared table | No table — only briscola card visible |
| Hand size | 3 (redeal when both empty) | 3 (draw 1 after each trick) |
| Card strength | Face value for captures | Special ranking: Ace > 3 > King > Knight > Jack > 7-2 |
| Trump suit | None | Yes — determined by briscola card |
| Must follow suit | N/A | **No** — play any card |
| Scoring | 5 categories per round | Simple point count (120 total) |
| Win threshold | First to 11 across rounds | 61+ points in single game |
| Visible info | Both hands hidden, table visible | Both hands hidden, only briscola card visible |
| Multi-round | Yes (rounds until 11 points) | No (single game, play again option) |

## Briscola-Specific UI Elements

- **Briscola card**: Displayed face-up beside the draw pile, rotated 90° (landscape orientation)
- **Trick area**: Central area where the two played cards appear during a trick
- **Won-tricks pile**: Each player's pile shows total points captured (not individual cards)
- **No table cards grid**: Unlike Scopa, there is no table card area

## AI Strategy (Briscola)

Key considerations for AI:
- **Trump management**: Save trump cards for capturing high-value tricks
- **Point conservation**: Avoid playing point cards (Ace, 3, King, Knight, Jack) when likely to lose
- **Card counting**: Track which high-value cards have been played
- **Lead vs follow**: Different strategy when leading (play low) vs following (capture or sacrifice)
- **Endgame**: With no draw pile, all remaining cards are known — play optimally
- **Briscola pickup**: The last card drawn is the visible briscola — factor this into strategy
