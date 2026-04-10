import { describe, it, expect } from 'vitest'
import { formatTressetteScore } from './card'

describe('formatTressetteScore', () => {
  it('formats exact multiples of 3 as whole numbers', () => {
    expect(formatTressetteScore(0)).toBe('0')
    expect(formatTressetteScore(3)).toBe('1')
    expect(formatTressetteScore(6)).toBe('2')
    expect(formatTressetteScore(33)).toBe('11')
  })

  it('formats remainder 1 as 1/3 fraction', () => {
    expect(formatTressetteScore(1)).toBe('1/3')
    expect(formatTressetteScore(4)).toBe('1 1/3')
    expect(formatTressetteScore(10)).toBe('3 1/3')
    expect(formatTressetteScore(34)).toBe('11 1/3')
  })

  it('formats remainder 2 as 2/3 fraction', () => {
    expect(formatTressetteScore(2)).toBe('2/3')
    expect(formatTressetteScore(5)).toBe('1 2/3')
    expect(formatTressetteScore(29)).toBe('9 2/3')
    expect(formatTressetteScore(35)).toBe('11 2/3')
  })

  it('handles the maximum tressette score (35 points)', () => {
    // 35 total points = 105 in x3 system
    expect(formatTressetteScore(105)).toBe('35')
  })
})
