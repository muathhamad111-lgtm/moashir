import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { contactMessagesService } from '@/services/contactMessagesService'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { usePortalTab } from '@/composables/usePortalTab'
import { MESSAGE_TAB_FILTERS, MESSAGE_TABS } from '@/constants/messages'

const DEFAULT_PER_PAGE = 20

function openCount(statusCounts) {
  if (!statusCounts) return null
  return (statusCounts.new ?? 0) + (statusCounts.inProgress ?? 0) + (statusCounts.replied ?? 0)
}

export function useMyMessages({ perPage = DEFAULT_PER_PAGE } = {}) {
  const { t } = useI18n()
  const counts = usePortalCountsStore()
  const { tab } = usePortalTab(Object.values(MESSAGE_TABS))
  const items = ref([])
  const status = ref('loading')
  const errorMessage = ref('')
  const statusCounts = ref(null)
  const page = ref(1)
  const lastPage = ref(1)

  const tabs = computed(() => [
    {
      value: MESSAGE_TABS.OPEN,
      label: t('portal.messages.tabs.open'),
      count: openCount(statusCounts.value),
    },
    {
      value: MESSAGE_TABS.CLOSED,
      label: t('portal.messages.tabs.closed'),
      count: statusCounts.value?.closed ?? null,
    },
  ])

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await contactMessagesService.listMine({
        'filter[status]': MESSAGE_TAB_FILTERS[tab.value],
        page: page.value,
        perPage,
      })
      items.value = data ?? []
      statusCounts.value = meta?.statusCounts ?? null
      lastPage.value = meta?.pagination?.lastPage ?? 1
      counts.setUnreadReplies(meta?.unreadReplies ?? 0)
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function setPage(next) {
    if (next < 1 || next > lastPage.value || next === page.value) return
    page.value = next
  }

  function applyThread(thread) {
    items.value = items.value.map((item) =>
      item.id === thread.id ? { ...item, ...withoutEntries(thread) } : item,
    )
  }

  watch(tab, () => {
    if (page.value === 1) load()
    else page.value = 1
  })
  watch(page, load)
  onMounted(load)

  return { tab, tabs, items, status, errorMessage, page, lastPage, load, setPage, applyThread }
}

function withoutEntries(thread) {
  const card = { ...thread }
  delete card.entries
  return card
}
