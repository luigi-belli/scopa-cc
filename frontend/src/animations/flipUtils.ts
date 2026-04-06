import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath, cardBackPath } from '@/types/card'

export function snapshotPositions(container: HTMLElement, selector: string): Map<string, DOMRect> {
  const map = new Map<string, DOMRect>()
  const elements = container.querySelectorAll(selector)
  elements.forEach((el) => {
    const key = (el as HTMLElement).dataset.cardKey
    if (key) {
      map.set(key, el.getBoundingClientRect())
    }
  })
  return map
}

export function animateFLIP(
  before: Map<string, DOMRect>,
  after: Map<string, DOMRect>,
  container: HTMLElement,
  selector: string,
  duration: number,
  easing: string
): Promise<void> {
  const promises: Promise<void>[] = []
  const elements = container.querySelectorAll(selector)

  elements.forEach((el) => {
    const key = (el as HTMLElement).dataset.cardKey
    if (!key) return

    const first = before.get(key)
    const last = after.get(key)

    if (!first || !last) return

    const dx = first.left - last.left
    const dy = first.top - last.top

    if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return

    const anim = el.animate(
      [
        { transform: `translate(${dx}px, ${dy}px)` },
        { transform: 'translate(0, 0)' },
      ],
      { duration, easing, fill: 'none' }
    )

    promises.push(new Promise((resolve) => {
      anim.onfinish = () => resolve()
    }))
  })

  return Promise.all(promises).then(() => {})
}

export function createCardClone(
  card: Card,
  rect: DOMRect,
  deckStyle: DeckStyle
): HTMLElement {
  const clone = document.createElement('div')
  clone.style.position = 'fixed'
  clone.style.left = `${rect.left}px`
  clone.style.top = `${rect.top}px`
  clone.style.width = `${rect.width}px`
  clone.style.height = `${rect.height}px`
  clone.style.zIndex = '51'
  clone.style.borderRadius = '6px'
  clone.style.overflow = 'hidden'
  clone.style.pointerEvents = 'none'

  const img = document.createElement('img')
  img.src = cardImagePath(card, deckStyle)
  img.style.width = '100%'
  img.style.height = '100%'
  img.style.objectFit = 'cover'
  img.style.display = 'block'
  clone.appendChild(img)

  return clone
}

export function createCardBackClone(
  rect: DOMRect,
  deckStyle: DeckStyle
): HTMLElement {
  const clone = document.createElement('div')
  clone.style.position = 'fixed'
  clone.style.left = `${rect.left}px`
  clone.style.top = `${rect.top}px`
  clone.style.width = `${rect.width}px`
  clone.style.height = `${rect.height}px`
  clone.style.zIndex = '52'
  clone.style.borderRadius = '6px'
  clone.style.overflow = 'hidden'
  clone.style.pointerEvents = 'none'

  const img = document.createElement('img')
  img.src = cardBackPath(deckStyle)
  img.style.width = '100%'
  img.style.height = '100%'
  img.style.objectFit = 'cover'
  img.style.display = 'block'
  clone.appendChild(img)

  return clone
}

export function animateClone(
  clone: HTMLElement,
  from: DOMRect,
  to: DOMRect,
  duration: number,
  easing: string,
  scale?: number
): Promise<void> {
  const keyframes: Keyframe[] = [
    {
      left: `${from.left}px`,
      top: `${from.top}px`,
      width: `${from.width}px`,
      height: `${from.height}px`,
      transform: 'scale(1)',
    },
    {
      left: `${to.left}px`,
      top: `${to.top}px`,
      width: scale ? `${to.width * scale}px` : `${to.width}px`,
      height: scale ? `${to.height * scale}px` : `${to.height}px`,
      transform: scale ? `scale(${scale})` : 'scale(1)',
    },
  ]

  const anim = clone.animate(keyframes, {
    duration,
    easing,
    fill: 'forwards',
  })

  return new Promise((resolve) => {
    anim.onfinish = () => resolve()
  })
}

/** Table grid dimensions: 2 rows x 5 columns (matches CSS grid-template in .table-center) */
const TABLE_COLS = 5
const TABLE_MAX_ROWS = 2

/** Parameters describing the table grid geometry, read from CSS computed style. */
export interface SlotGridParams {
  /** Column width (from gridTemplateColumns) */
  colW: number
  /** Row height (from gridTemplateRows) */
  rowH: number
  /** Grid gap between cells */
  gap: number
  /** Left padding of the container (for deck offset) */
  padLeft: number
  /** Number of rows currently in the grid template */
  rowCount: number
}

/** Compute the bounding rect for slot `index` in the 2x5 table grid.
 *  Pure geometry -- no DOM access needed. */
export function computeSlotRect(
  index: number,
  cardW: number,
  cardH: number,
  containerRect: DOMRect,
  grid: SlotGridParams
): DOMRect {
  const { colW, rowH, gap, padLeft, rowCount } = grid

  const gridW = TABLE_COLS * colW + (TABLE_COLS - 1) * gap
  const gridH = (rowCount >= TABLE_MAX_ROWS ? TABLE_MAX_ROWS * rowH + gap : rowH)
  const contentLeft = containerRect.left + padLeft + (containerRect.width - padLeft - gridW) / 2
  const contentTop  = containerRect.top + (containerRect.height - gridH) / 2

  const col = index % TABLE_COLS
  const row = Math.floor(index / TABLE_COLS)
  const left = contentLeft + col * (colW + gap) + (colW - cardW) / 2
  const top  = contentTop  + row * (rowH + gap) + (rowH - cardH) / 2

  return new DOMRect(left, top, cardW, cardH)
}

export function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export function cardKey(card: Card, index?: number): string {
  return `${card.value}-${card.suit}${index !== undefined ? `-${index}` : ''}`
}
