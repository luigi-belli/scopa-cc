import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { ApiError, isRetryable, RETRY_BASE_DELAY } from './useApi'

async function requestWithRetry<T>(
  doRequest: () => Promise<T>,
  maxRetries: number,
): Promise<T> {
  let lastError: unknown
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    if (attempt > 0) {
      const delay = RETRY_BASE_DELAY * Math.pow(2, attempt - 1)
      // Skip actual delay in tests
      await Promise.resolve()
    }
    try {
      lastError = undefined
      return await doRequest()
    } catch (e) {
      lastError = e
      if (!isRetryable(e) || attempt >= maxRetries) break
    }
  }
  throw lastError
}

describe('useApi — retry logic', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('isRetryable', () => {
    it('returns true for 500 status', () => {
      expect(isRetryable(new ApiError('fail', 500))).toBe(true)
    })

    it('returns true for 502 status', () => {
      expect(isRetryable(new ApiError('fail', 502))).toBe(true)
    })

    it('returns true for 503 status', () => {
      expect(isRetryable(new ApiError('fail', 503))).toBe(true)
    })

    it('returns true for 429 rate limit', () => {
      expect(isRetryable(new ApiError('fail', 429))).toBe(true)
    })

    it('returns false for 400 client error', () => {
      expect(isRetryable(new ApiError('fail', 400))).toBe(false)
    })

    it('returns false for 403 forbidden', () => {
      expect(isRetryable(new ApiError('fail', 403))).toBe(false)
    })

    it('returns false for 404 not found', () => {
      expect(isRetryable(new ApiError('fail', 404))).toBe(false)
    })

    it('returns false for 409 conflict', () => {
      expect(isRetryable(new ApiError('fail', 409))).toBe(false)
    })

    it('returns true for TypeError (network failure)', () => {
      expect(isRetryable(new TypeError('Failed to fetch'))).toBe(true)
    })

    it('returns true for AbortError (timeout)', () => {
      const err = new DOMException('signal is aborted', 'AbortError')
      expect(isRetryable(err)).toBe(true)
    })

    it('returns false for generic Error', () => {
      expect(isRetryable(new Error('random'))).toBe(false)
    })
  })

  describe('retry behavior', () => {
    it('succeeds immediately without retries when request succeeds', async () => {
      const fn = vi.fn().mockResolvedValue('ok')
      const result = await requestWithRetry(fn, 2)
      expect(result).toBe('ok')
      expect(fn).toHaveBeenCalledTimes(1)
    })

    it('retries on 500 error and succeeds on second attempt', async () => {
      const fn = vi.fn()
        .mockRejectedValueOnce(new ApiError('fail', 500))
        .mockResolvedValue('ok')
      const result = await requestWithRetry(fn, 2)
      expect(result).toBe('ok')
      expect(fn).toHaveBeenCalledTimes(2)
    })

    it('retries on TypeError (network error) and succeeds', async () => {
      const fn = vi.fn()
        .mockRejectedValueOnce(new TypeError('Failed to fetch'))
        .mockResolvedValue('ok')
      const result = await requestWithRetry(fn, 1)
      expect(result).toBe('ok')
      expect(fn).toHaveBeenCalledTimes(2)
    })

    it('does NOT retry on 400 error', async () => {
      const fn = vi.fn().mockRejectedValue(new ApiError('bad', 400))
      await expect(requestWithRetry(fn, 2)).rejects.toThrow('bad')
      expect(fn).toHaveBeenCalledTimes(1)
    })

    it('does NOT retry on 409 conflict', async () => {
      const fn = vi.fn().mockRejectedValue(new ApiError('conflict', 409))
      await expect(requestWithRetry(fn, 2)).rejects.toThrow('conflict')
      expect(fn).toHaveBeenCalledTimes(1)
    })

    it('exhausts all retries and throws the last error', async () => {
      const fn = vi.fn().mockRejectedValue(new ApiError('fail', 503))
      await expect(requestWithRetry(fn, 2)).rejects.toThrow('fail')
      expect(fn).toHaveBeenCalledTimes(3) // 1 initial + 2 retries
    })

    it('retries up to maxRetries times on transient errors', async () => {
      const fn = vi.fn().mockRejectedValue(new TypeError('net err'))
      await expect(requestWithRetry(fn, 3)).rejects.toThrow('net err')
      expect(fn).toHaveBeenCalledTimes(4) // 1 initial + 3 retries
    })

    it('succeeds on the last retry attempt', async () => {
      const fn = vi.fn()
        .mockRejectedValueOnce(new ApiError('fail', 500))
        .mockRejectedValueOnce(new ApiError('fail', 500))
        .mockResolvedValue('finally')
      const result = await requestWithRetry(fn, 2)
      expect(result).toBe('finally')
      expect(fn).toHaveBeenCalledTimes(3)
    })

    it('does not retry when maxRetries is 0', async () => {
      const fn = vi.fn().mockRejectedValue(new ApiError('fail', 500))
      await expect(requestWithRetry(fn, 0)).rejects.toThrow('fail')
      expect(fn).toHaveBeenCalledTimes(1)
    })
  })
})

describe('ApiError', () => {
  it('captures status code', () => {
    const err = new ApiError('test', 418)
    expect(err.status).toBe(418)
    expect(err.message).toBe('test')
    expect(err.name).toBe('ApiError')
  })

  it('is an instance of Error', () => {
    const err = new ApiError('test', 500)
    expect(err).toBeInstanceOf(Error)
    expect(err).toBeInstanceOf(ApiError)
  })
})
