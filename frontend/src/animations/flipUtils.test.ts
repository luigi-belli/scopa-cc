import { describe, it, expect } from 'vitest'
import type { SlotGridParams } from './flipUtils'

// DOMRect is not available in Node -- provide a minimal polyfill
if (typeof globalThis.DOMRect === 'undefined') {
  class DOMRectPolyfill {
    readonly x: number
    readonly y: number
    readonly width: number
    readonly height: number
    constructor(x = 0, y = 0, width = 0, height = 0) {
      this.x = x; this.y = y; this.width = width; this.height = height
    }
    get left(): number { return this.x }
    get top(): number { return this.y }
    get right(): number { return this.x + this.width }
    get bottom(): number { return this.y + this.height }
    toJSON(): Record<string, number> {
      return { x: this.x, y: this.y, width: this.width, height: this.height }
    }
  }
  globalThis.DOMRect = DOMRectPolyfill as unknown as typeof DOMRect
}

import { computeSlotRect, computeFlyToDelta } from './flipUtils'

describe('computeSlotRect', () => {
  // Standard desktop dimensions from CSS
  const CARD_W = 75
  const CARD_H = 133

  const grid: SlotGridParams = {
    colW: 75,
    rowH: 133,
    gap: 6,
    padLeft: 70,
    rowCount: 2,
    colCount: 5,
  }

  // Container: 900px wide, 340px tall, at origin
  const containerRect = new DOMRect(0, 0, 900, 340)

  // Precomputed expected grid origin
  const gridW = 5 * grid.colW + 4 * grid.gap // 399
  const gridH = 2 * grid.rowH + grid.gap     // 272
  const contentLeft = grid.padLeft + (containerRect.width - grid.padLeft - gridW) / 2 // 285.5
  const contentTop = (containerRect.height - gridH) / 2                                // 34

  it('places slot 0 (empty table) at first grid cell, not center', () => {
    const rect = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)

    expect(rect.left).toBeCloseTo(contentLeft)
    expect(rect.top).toBeCloseTo(contentTop)
    expect(rect.width).toBe(CARD_W)
    expect(rect.height).toBe(CARD_H)

    // Must NOT be centered in the container (the old bug)
    const centerX = containerRect.width / 2 - CARD_W / 2
    expect(rect.left).not.toBeCloseTo(centerX, 0)
  })

  it('places slot 1 one column to the right of slot 0', () => {
    const r0 = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)
    const r1 = computeSlotRect(1, CARD_W, CARD_H, containerRect, grid)

    expect(r1.left - r0.left).toBeCloseTo(grid.colW + grid.gap)
    expect(r1.top).toBeCloseTo(r0.top)
  })

  it('places slot 5 in second row, first column', () => {
    const r0 = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)
    const r5 = computeSlotRect(5, CARD_W, CARD_H, containerRect, grid)

    expect(r5.left).toBeCloseTo(r0.left)
    expect(r5.top - r0.top).toBeCloseTo(grid.rowH + grid.gap)
  })

  it('uses mobile dimensions correctly', () => {
    const mobileContainer = new DOMRect(0, 0, 400, 250)
    const mobileGrid: SlotGridParams = {
      colW: 58,
      rowH: 103,
      gap: 6,
      padLeft: 50,
      rowCount: 2,
      colCount: 5,
    }

    const rect = computeSlotRect(0, 58, 103, mobileContainer, mobileGrid)

    expect(rect.left).toBeGreaterThan(0)
    expect(rect.top).toBeGreaterThan(0)
    expect(rect.left + rect.width).toBeLessThan(mobileContainer.width)
  })

  it('mobile deck visual does not overlap table grid', () => {
    // Mobile: deck visual is 40px wide at left:8px → extends to 48px
    // Table grid padding-left is 50px → grid starts at 50px
    const deckLeft = 8
    const deckWidth = 40  // mobile deck visual size
    const deckRight = deckLeft + deckWidth  // 48px
    const gridPadLeft = 50  // mobile padding-left

    const mobileContainer = new DOMRect(0, 0, 400, 250)
    const mobileGrid: SlotGridParams = {
      colW: 58,
      rowH: 103,
      gap: 6,
      padLeft: gridPadLeft,
      rowCount: 2,
      colCount: 5,
    }
    const slot0 = computeSlotRect(0, 58, 103, mobileContainer, mobileGrid)

    // Deck must not overlap the first table card slot
    expect(deckRight).toBeLessThanOrEqual(gridPadLeft)
    expect(deckRight).toBeLessThanOrEqual(slot0.left)
  })

  it('desktop deck visual does not overlap table grid', () => {
    // Desktop: deck is 75px wide at left:12px → extends to 87px
    // But deck is position:absolute so padding-left:70px is the grid offset
    const deckLeft = 12
    const deckWidth = 75
    const deckRight = deckLeft + deckWidth  // 87px

    const slot0 = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)

    // On desktop, first slot starts well past the deck due to centering
    expect(slot0.left).toBeGreaterThan(deckRight)
  })

  it('places briscola single slot centered in container', () => {
    const briscolaGrid: SlotGridParams = {
      colW: 75,
      rowH: 133,
      gap: 6,
      padLeft: 70,
      rowCount: 1,
      colCount: 1,
    }
    const rect = computeSlotRect(0, CARD_W, CARD_H, containerRect, briscolaGrid)

    // Single column: gridW = 75, centered in (900 - 70) = 830 available width
    const expectedGridW = 75
    const expectedLeft = 70 + (900 - 70 - expectedGridW) / 2
    expect(rect.left).toBeCloseTo(expectedLeft)
    // Vertically centered
    const expectedTop = (340 - 133) / 2
    expect(rect.top).toBeCloseTo(expectedTop)
  })

  it('is a pure function -- same inputs always give same outputs', () => {
    const r0a = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)
    computeSlotRect(3, CARD_W, CARD_H, containerRect, grid)
    const r0b = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)

    expect(r0a.left).toBe(r0b.left)
    expect(r0a.top).toBe(r0b.top)
  })
})

describe('computeFlyToDelta', () => {
  it('centers clone on target when scale is 1 (desktop, same-size cards)', () => {
    // Desktop: deck and cards are both 75×133
    const { dx, dy, sx, sy } = computeFlyToDelta(
      12, 100, 75, 133,  // from: deck at (12, 100), size 75×133
      { left: 300, top: 50, width: 75, height: 133 },  // to: card slot
    )
    expect(sx).toBe(1)
    expect(sy).toBe(1)
    // Clone top-left should move exactly to target top-left
    expect(dx).toBeCloseTo(300 - 12)
    expect(dy).toBeCloseTo(50 - 100)
  })

  it('centers clone on target when scaling up (mobile deal animation)', () => {
    // Mobile: deck visual is 40×71, card slots are 58×103
    const deckL = 8, deckT = 90, deckW = 40, deckH = 71
    const target = { left: 100, top: 50, width: 58, height: 103 }
    const scale = target.width / deckW  // 58/40 = 1.45

    const { dx, dy, sx, sy } = computeFlyToDelta(deckL, deckT, deckW, deckH, target, scale)

    expect(sx).toBeCloseTo(1.45)
    expect(sy).toBeCloseTo(103 / 71)

    // After CSS transform: translate(dx,dy) scale(sx,sy) with transform-origin center,
    // the visual center of the clone should land on the target's center.
    // Visual center = fromL + fromW/2 + dx, fromT + fromH/2 + dy
    const visualCenterX = deckL + deckW / 2 + dx
    const visualCenterY = deckT + deckH / 2 + dy
    const targetCenterX = target.left + target.width / 2
    const targetCenterY = target.top + target.height / 2

    expect(visualCenterX).toBeCloseTo(targetCenterX)
    expect(visualCenterY).toBeCloseTo(targetCenterY)
  })

  it('centers clone on target when scaling down (sweep to captured on mobile)', () => {
    // Card (58×103) sweeping to captured deck (40×71)
    const fromL = 100, fromT = 50, fromW = 58, fromH = 103
    const target = { left: 8, top: 200, width: 40, height: 71 }
    const scale = target.width / fromW  // 40/58 ≈ 0.69

    const { dx, dy } = computeFlyToDelta(fromL, fromT, fromW, fromH, target, scale)

    const visualCenterX = fromL + fromW / 2 + dx
    const visualCenterY = fromT + fromH / 2 + dy
    const targetCenterX = target.left + target.width / 2
    const targetCenterY = target.top + target.height / 2

    expect(visualCenterX).toBeCloseTo(targetCenterX)
    expect(visualCenterY).toBeCloseTo(targetCenterY)
  })

  it('no scale parameter behaves same as scale=1', () => {
    const fromL = 50, fromT = 80, fromW = 75, fromH = 133
    const target = { left: 200, top: 40, width: 75, height: 133 }

    const withoutScale = computeFlyToDelta(fromL, fromT, fromW, fromH, target)
    const withScale1 = computeFlyToDelta(fromL, fromT, fromW, fromH, target, 1)

    expect(withoutScale.dx).toBeCloseTo(withScale1.dx)
    expect(withoutScale.dy).toBeCloseTo(withScale1.dy)
    expect(withoutScale.sx).toBe(1)
    expect(withoutScale.sy).toBe(1)
  })
})
