import { defineStore } from 'pinia'
import { profileService } from '@/services/profileService'
import { TABLE_COLUMNS_CACHE_KEY } from '@/constants/tableColumns'
import { readStored, writeStored } from '@/utils/safeStorage'
import { logger } from '@/utils/logger'

function cacheKeyOf(userId) {
  return `${TABLE_COLUMNS_CACHE_KEY}.${userId}`
}

function cachedChoices(userId) {
  try {
    const parsed = JSON.parse(readStored(cacheKeyOf(userId)) ?? 'null')
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
  } catch {
    return {}
  }
}

// The columns each user keeps visible per list table. The account holds them (they follow the user
// to every device); this browser keeps a copy so a list opens with them before the account answers.
export const useTableColumnsStore = defineStore('tableColumns', {
  state: () => ({ ownerId: null, choices: {}, loaded: false }),
  actions: {
    claim(userId) {
      if (this.ownerId === userId) return
      this.ownerId = userId
      this.choices = userId ? cachedChoices(userId) : {}
      this.loaded = false
      if (userId) this.load()
    },
    async load() {
      const owner = this.ownerId
      try {
        const { data } = await profileService.getPreferences()
        if (this.ownerId !== owner) return
        this.choices = { ...data?.tableColumns }
        this.loaded = true
        writeStored(cacheKeyOf(owner), JSON.stringify(this.choices))
      } catch (error) {
        logger.breadcrumb('table-columns', 'load.failed', { message: error?.message })
      }
    },
    // A list of columns keeps them; null goes back to the table's defaults.
    async choose(tableKey, columns) {
      const next = { ...this.choices }
      if (columns === null) delete next[tableKey]
      else next[tableKey] = [...columns]
      this.choices = next
      if (this.ownerId) writeStored(cacheKeyOf(this.ownerId), JSON.stringify(next))
      try {
        await profileService.updatePreferences({ tableColumns: { [tableKey]: columns } })
      } catch (error) {
        logger.breadcrumb('table-columns', 'save.failed', { message: error?.message })
      }
    },
  },
})
