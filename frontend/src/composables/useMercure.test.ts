import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'

// Test the safeCall pattern and staleness logic extracted from useMercure

describe('useMercure — safeCall pattern', () => {
  /** Reproduces the safeCall wrapper from useMercure */
  function safeCall(fn: () => unknown): void {
    try {
      const result = fn()
      if (result instanceof Promise) {
        result.catch(e => console.error('Mercure handler async error:', e))
      }
    } catch (e) {
      console.error('Mercure handler error:', e)
    }
  }

  it('catches synchronous exceptions from handlers', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
    const badHandler = () => { throw new Error('sync boom') }

    // Should not throw
    expect(() => safeCall(badHandler)).not.toThrow()
    expect(spy).toHaveBeenCalledWith('Mercure handler error:', expect.any(Error))
    spy.mockRestore()
  })

  it('catches rejected promises from async handlers', async () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
    const asyncBadHandler = async () => { throw new Error('async boom') }

    // Should not throw
    expect(() => safeCall(asyncBadHandler)).not.toThrow()

    // Allow microtask to process the rejection
    await new Promise(r => setTimeout(r, 0))
    expect(spy).toHaveBeenCalledWith('Mercure handler async error:', expect.any(Error))
    spy.mockRestore()
  })

  it('passes through return values from successful handlers', () => {
    const goodHandler = () => 'ok'
    expect(() => safeCall(goodHandler)).not.toThrow()
  })

  it('handles undefined/void return values', () => {
    const voidHandler = () => {}
    expect(() => safeCall(voidHandler)).not.toThrow()
  })
})

describe('useMercure — staleness detection logic', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const STALE_TIMEOUT = 45_000

  it('triggers reconnect callback after staleness timeout', () => {
    const onStale = vi.fn()
    let timer: ReturnType<typeof setTimeout> | null = null

    function touchActivity() {
      if (timer) clearTimeout(timer)
      timer = setTimeout(onStale, STALE_TIMEOUT)
    }

    touchActivity()
    expect(onStale).not.toHaveBeenCalled()

    // Advance to just before timeout
    vi.advanceTimersByTime(STALE_TIMEOUT - 1)
    expect(onStale).not.toHaveBeenCalled()

    // Advance past timeout
    vi.advanceTimersByTime(2)
    expect(onStale).toHaveBeenCalledTimes(1)

    if (timer) clearTimeout(timer)
  })

  it('resets staleness timer on activity', () => {
    const onStale = vi.fn()
    let timer: ReturnType<typeof setTimeout> | null = null

    function touchActivity() {
      if (timer) clearTimeout(timer)
      timer = setTimeout(onStale, STALE_TIMEOUT)
    }

    touchActivity()

    // Advance 30s (not yet stale)
    vi.advanceTimersByTime(30_000)
    expect(onStale).not.toHaveBeenCalled()

    // Reset timer (simulating an incoming event)
    touchActivity()

    // Advance another 30s (still within new timeout window)
    vi.advanceTimersByTime(30_000)
    expect(onStale).not.toHaveBeenCalled()

    // Advance the remaining 15s to trigger
    vi.advanceTimersByTime(15_000)
    expect(onStale).toHaveBeenCalledTimes(1)

    if (timer) clearTimeout(timer)
  })

  it('does not trigger if timer is cleared (disconnect)', () => {
    const onStale = vi.fn()
    let timer: ReturnType<typeof setTimeout> | null = null

    function touchActivity() {
      if (timer) clearTimeout(timer)
      timer = setTimeout(onStale, STALE_TIMEOUT)
    }

    touchActivity()

    vi.advanceTimersByTime(20_000)
    // Simulate disconnect — clear timer
    if (timer) { clearTimeout(timer); timer = null }

    vi.advanceTimersByTime(60_000)
    expect(onStale).not.toHaveBeenCalled()
  })
})

describe('useMercure — reconnection on stale', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('schedules reconnect after stale timeout with 1s delay', () => {
    const STALE_TIMEOUT = 45_000
    const reconnect = vi.fn()
    let staleTimer: ReturnType<typeof setTimeout> | null = null
    let connected = true

    function touchActivity() {
      if (staleTimer) clearTimeout(staleTimer)
      staleTimer = setTimeout(() => {
        connected = false
        // Schedule reconnect after 1s
        setTimeout(reconnect, 1000)
      }, STALE_TIMEOUT)
    }

    touchActivity()

    // Trigger stale
    vi.advanceTimersByTime(STALE_TIMEOUT)
    expect(connected).toBe(false)
    expect(reconnect).not.toHaveBeenCalled()

    // 1s delay for reconnect
    vi.advanceTimersByTime(1000)
    expect(reconnect).toHaveBeenCalledTimes(1)

    if (staleTimer) clearTimeout(staleTimer)
  })
})

describe('useMercure — parseEvent safety', () => {
  function parseEvent(data: string): unknown {
    try {
      const payload = JSON.parse(data)
      return payload.data ?? payload
    } catch {
      return null
    }
  }

  it('parses valid JSON with data wrapper', () => {
    const result = parseEvent('{"data": {"state": "playing"}}')
    expect(result).toEqual({ state: 'playing' })
  })

  it('parses valid JSON without data wrapper', () => {
    const result = parseEvent('{"state": "playing"}')
    expect(result).toEqual({ state: 'playing' })
  })

  it('returns null for invalid JSON', () => {
    const result = parseEvent('not json')
    expect(result).toBeNull()
  })

  it('returns null for empty string', () => {
    const result = parseEvent('')
    expect(result).toBeNull()
  })

  it('handles nested data correctly', () => {
    const result = parseEvent('{"data": {"myHand": [{"suit": "Denari", "value": 7}]}}')
    expect(result).toEqual({ myHand: [{ suit: 'Denari', value: 7 }] })
  })
})
