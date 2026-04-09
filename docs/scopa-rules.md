# Scopa — Game Rules

## Overview

Scopa ("sweep" in Italian) is a traditional Italian table-capture card game. Players take turns playing cards from their hand to capture cards from the table by matching values.

## The Deck

- **40 cards** in 4 suits: **Denari** (coins), **Coppe** (cups), **Bastoni** (clubs), **Spade** (swords)
- Values 1-10 per suit (8=Fante/Jack, 9=Cavallo/Knight, 10=Re/King)
- Card image naming: `{value}{suit_letter}.{ext}` where suit letters are: `d`=Denari, `c`=Coppe, `b`=Bastoni, `s`=Spade

## Deal

- 4 cards dealt face-up to the table
- 3 cards dealt to each player
- When both players' hands are empty and the deck has cards, deal 3 more to each (no new table cards)
- The non-dealer goes first; dealer alternates each round

## Turn Flow

1. Player plays one card from their hand
2. **Capture priority rule**: If the played card's value matches any single table card, **must capture that card** (cannot choose a multi-card sum instead)
3. If multiple single-card matches exist, player chooses which one to capture
4. If no single-card match but a combination of table cards sums to the played value, capture those cards
5. If multiple sum combinations exist, player chooses which set
6. If no captures possible, card is placed on the table
7. **Scopa**: If a capture clears all cards from the table, that's a scopa (worth 1 point). Does NOT count on the very last play of a round.
8. **End of round**: When all cards are played and deck is empty, the last player to capture gets remaining table cards (not a scopa)

## Scoring (per round)

| Category | Rule |
|---|---|
| **Carte** | Most total captured cards (1 point, 0 if tied) |
| **Denari** | Most Denari-suit cards captured (1 point, 0 if tied) |
| **Sette Bello** | Captured the 7 of Denari (always 1 point to holder) |
| **Primiera** | Highest sum of best primiera-value card per suit (1 point) |
| **Scope** | 1 point per scopa made during the round |

## Primiera Values

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

## Winning

- Game to **11 points** across multiple rounds
- If both reach 11+ in the same round, points are counted in order: **Carte → Denari → Settebello → Primiera → Scope**. The first player to reach 11 in this counting order wins.
- If still tied after all categories (both players cross 11 on the same category, e.g. scope), play continues with another round until one player is ahead

## Game States

| State | Description |
|---|---|
| `waiting` | Waiting for second player to join |
| `playing` | Active play — current player must play a card |
| `choosing` | Player must choose between multiple capture options |
| `round-end` | Round finished, showing scores before next round |
| `game-over` | Game finished, showing final results |
| `finished` | Game cleaned up after game-over |

## Scopa-Specific Mechanics

- **Table cards**: Visible to both players, central to gameplay
- **Capture choice**: When multiple captures are possible, player must choose (triggers `choosing` state)
- **Scopa sweep**: Clearing the table earns bonus points
- **Multi-round**: Game spans multiple rounds until a player reaches 11+ points
- **Re-deal**: When hands are empty but deck has cards, deal 3 more to each player (no new table cards)

## AI Strategy (Scopa)

Evaluates every legal play with multi-factor scoring:
- **Capture weights**: +10/card, +15/denari, +100 sette bello, +0.5×primiera, +12/seven, +6/six, +80 scopa, +3/multi-card
- **Placement weights**: -0.8×primiera, -120 sette bello, -20 denari, -25 sevens, -30 easy scopa, -15 matching, +5 face cards
