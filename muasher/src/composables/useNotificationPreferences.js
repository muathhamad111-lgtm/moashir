import { computed, ref } from 'vue'
import { notificationsService } from '@/services/notificationsService'
import { EDITABLE_NOTIFICATION_CHANNELS } from '@/constants/notifications'

function optionalChannels(row) {
  return row.channels.filter((channel) => EDITABLE_NOTIFICATION_CHANNELS.includes(channel.channel))
}

function draftKey(code, channel) {
  return `${code}|${channel}`
}

function draftOf(rows) {
  return Object.fromEntries(
    rows.flatMap((row) =>
      optionalChannels(row).map((channel) => [
        draftKey(row.code, channel.channel),
        channel.enabled,
      ]),
    ),
  )
}

function groupByModule(rows) {
  const modules = [...new Set(rows.map((row) => row.module))]
  return modules.map((module) => ({ module, rows: rows.filter((row) => row.module === module) }))
}

export function useNotificationPreferences() {
  const rows = ref([])
  const draft = ref({})
  const saved = ref({})
  const status = ref('idle')
  const saving = ref(false)
  const errorMessage = ref('')

  const editableRows = computed(() => rows.value.filter((row) => optionalChannels(row).length > 0))
  const groups = computed(() => groupByModule(editableRows.value))
  const changes = computed(() =>
    Object.entries(draft.value)
      .filter(([key, enabled]) => saved.value[key] !== enabled)
      .map(([key, enabled]) => {
        const [code, channel] = key.split('|')
        return { code, channel, enabled }
      }),
  )

  function apply(nextRows) {
    rows.value = nextRows
    saved.value = draftOf(nextRows)
    draft.value = { ...saved.value }
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      apply((await notificationsService.getPreferences()).data)
      status.value = editableRows.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function isEnabled(code, channel) {
    return draft.value[draftKey(code, channel)] === true
  }

  function setEnabled(code, channel, enabled) {
    draft.value = { ...draft.value, [draftKey(code, channel)]: enabled }
  }

  async function save() {
    if (!changes.value.length || saving.value) return true
    saving.value = true
    errorMessage.value = ''
    try {
      apply((await notificationsService.updatePreferences(changes.value)).data)
      return true
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      return false
    } finally {
      saving.value = false
    }
  }

  return { groups, status, saving, errorMessage, changes, load, isEnabled, setEnabled, save }
}
