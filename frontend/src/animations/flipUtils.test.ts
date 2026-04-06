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

import { computeSlotRect } from './flipUtils'

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
    }

    const rect = computeSlotRect(0, 58, 103, mobileContainer, mobileGrid)

    expect(rect.left).toBeGreaterThan(0)
    expect(rect.top).toBeGreaterThan(0)
    expect(rect.left + rect.width).toBeLessThan(mobileContainer.width)
  })

  it('is a pure function -- same inputs always give same outputs', () => {
    const r0a = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)
    computeSlotRect(3, CARD_W, CARD_H, containerRect, grid)
    const r0b = computeSlotRect(0, CARD_W, CARD_H, containerRect, grid)

    expect(r0a.left).toBe(r0b.left)
    expect(r0a.top).toBe(r0b.top)
  })
})
