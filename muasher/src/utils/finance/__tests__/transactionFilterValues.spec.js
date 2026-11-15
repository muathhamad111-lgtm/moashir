import { describe, expect, it } from 'vitest'
import {
  joinFilterValues,
  listFilterParams,
  listFiltersFromQuery,
  splitFilterValue,
} from '@/utils/finance/transactionFilterValues'

describe('transactionFilterValues', () => {
  it('splits and joins comma-separated values in the catalogue order', () => {
    expect(splitFilterValue('')).toEqual([])
    expect(splitFilterValue('paid,failed')).toEqual(['paid', 'failed'])
    expect(
      joinFilterValues(['refund', 'electronic', 'refund', 'other'], ['electronic', 'refund']),
    ).toBe('electronic,refund')
  })

  it('sends several types and statuses as lists and leaves other params alone', () => {
    expect(
      listFilterParams({
        'filter[type]': 'electronic,refund',
        'filter[status]': 'paid',
        'filter[purpose]': 'accredited',
      }),
    ).toEqual({
      'filter[type]': ['electronic', 'refund'],
      'filter[status]': ['paid'],
      'filter[purpose]': 'accredited',
    })
    expect(listFilterParams({ page: 2 })).toEqual({ page: 2 })
  })

  it('opens already filtered from a link and ignores unknown values', () => {
    expect(listFiltersFromQuery({ type: 'refund' })).toEqual({ type: 'refund' })
    expect(listFiltersFromQuery({ status: ['failed', 'paid'], type: 'nope' })).toEqual({
      status: 'paid,failed',
    })
    expect(listFiltersFromQuery(undefined)).toEqual({})
  })
})
