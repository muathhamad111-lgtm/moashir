import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ndaService } from '@/services/ndaService'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { downloadBlob } from '@/utils/download'

const PER_PAGE = 15

export const NDA_FILTERS = Object.freeze(['all', 'pending', 'accepted'])

// The register rows of the current version, filtered and paged.
function useNdaRegisterPage() {
  const filter = ref('all')
  const page = ref(1)
  const rows = ref([])
  const summary = ref({ inScope: 0, accepted: 0, pending: 0 })
  const version = ref(null)
  const lastPage = ref(1)
  const status = ref('loading')
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const params = { filter: filter.value, page: page.value, perPage: PER_PAGE }
      const envelope = await ndaService.acceptances(params)
      rows.value = envelope.data
      summary.value = envelope.meta.summary
      version.value = envelope.meta.version
      lastPage.value = envelope.meta.pagination?.lastPage ?? 1
      status.value = rows.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  watch(filter, () => {
    page.value = 1
    load()
  })
  watch(page, load)

  return { filter, page, rows, summary, version, lastPage, status, errorMessage, load }
}

export function useNdaRegister() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const register = useNdaRegisterPage()
  const busy = ref('')

  async function run(name, execute) {
    if (busy.value) return
    busy.value = name
    try {
      await execute()
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      busy.value = ''
    }
  }

  const exportCsv = () =>
    run('export', async () => {
      const params = { filter: register.filter.value }
      const { blob, fileName, rowCount } = await ndaService.exportAcceptances(params)
      downloadBlob(blob, fileName)
      toast.success(t('nda.register.exported', { count: rowCount }))
    })

  async function remind() {
    const accepted = await confirm({
      title: t('nda.register.remindTitle'),
      message: t('nda.register.remindMessage', { count: register.summary.value.pending }),
      confirmLabel: t('actions.send'),
    })
    if (!accepted) return
    await run('remind', async () => {
      const { data } = await ndaService.sendReminders()
      toast.success(t('nda.register.reminded', { count: data.recipients }))
    })
  }

  return { ...register, busy, exportCsv, remind }
}
