import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { notificationTextsService } from '@/services/notificationTextsService'
import { notificationTypesService } from '@/services/notificationTypesService'
import { useToast } from '@/composables/useToast'

function statesOfDetail(detail) {
  return (detail.channelSettings ?? []).reduce((states, row) => {
    states[row.audience] = { ...states[row.audience], [row.channel]: row.isEnabled }
    return states
  }, {})
}

/**
 * The channel switches of the notification catalogue: one switch per type, audience and channel, plus bulk
 * switches. Core types never switch (the server refuses and the bulk call skips them).
 */
export function useNotificationSwitchboard(types, replaceTypes) {
  const { t } = useI18n()
  const toast = useToast()
  const busy = ref(false)

  function isOn(type, audience, channel) {
    return Boolean(type.channelStates?.[audience]?.[channel])
  }

  function countOn(list, channel) {
    return list.filter((type) => type.audienceKinds.some((kind) => isOn(type, kind, channel)))
      .length
  }

  async function run(request) {
    busy.value = true
    try {
      await request()
      toast.success(t('settings.notifications.channels.saved'))
    } catch (error) {
      toast.error(error?.normalized?.message || t('settings.notifications.channels.saveFailed'))
    } finally {
      busy.value = false
    }
  }

  function switchOne({ type, audience, channel, enabled }) {
    return run(async () => {
      const { data } = await notificationTextsService.updateChannels(
        type.code,
        { [channel]: enabled },
        audience,
      )
      const next = { ...type, channelStates: statesOfDetail(data) }
      replaceTypes(types.value.map((row) => (row.code === type.code ? next : row)))
    })
  }

  function switchMany(change) {
    return run(async () => {
      const { data } = await notificationTypesService.bulkUpdate(change)
      replaceTypes(data)
    })
  }

  return { busy, isOn, countOn, switchOne, switchMany }
}
