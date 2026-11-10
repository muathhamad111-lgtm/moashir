import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toPriceHistoryItem, toPriceRow, toPricesSettings } from './mappers/prices'

const NOT_FOUND = 404

const { read, write } = domainRequests('pricesSettings', 'settings')

function rowPath(key) {
  return `/settings/prices/${encodeURIComponent(key)}`
}

function withCurrentRow(error) {
  const row = error?.normalized?.meta?.row
  if (row) error.normalized.meta = { ...error.normalized.meta, row: toPriceRow(row) }
  throw error
}

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const pricesSettingsService = {
  get() {
    return mapData(
      read('get', () => apiClient.get('/settings/prices')),
      toPricesSettings,
    )
  },

  history(key, params = {}) {
    const request = read(
      'history',
      () => apiClient.get(`${rowPath(key)}/history`, { params }),
      params,
    )
    return mapData(request, (items) => (items ?? []).map(toPriceHistoryItem))
  },

  update(key, payload) {
    return mapData(
      write('update', () => apiClient.put(rowPath(key), payload), payload),
      toPriceRow,
    ).catch(withCurrentRow)
  },

  cancelScheduled(key, payload = {}) {
    return mapData(
      write(
        'cancelScheduled',
        () => apiClient.delete(`${rowPath(key)}/scheduled`, { data: payload }),
        payload,
        [NOT_FOUND],
      ),
      toPriceRow,
    )
  },
}
