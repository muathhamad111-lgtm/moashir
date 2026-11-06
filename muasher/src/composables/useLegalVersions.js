import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { legalVersionsService } from '@/services/legalVersionsService'
import { useConfirm } from '@/composables/useConfirm'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { firstFieldErrors } from '@/utils/payments/financeErrors'
import {
  changedLegalDraft,
  legalDraftPayload,
  legalDraftSchema,
  legalDraftValues,
  suggestedVersionLabel,
} from '@/utils/legal/legalDraftForm'

const CONFLICT = 409
const MODES = Object.freeze({ LIST: 'list', VIEW: 'view', EDIT: 'edit', NEW: 'new' })
const DRAFT = 'draft'
const CURRENT = 'current'

export function useLegalVersions(kind) {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
  const versions = ref([])
  const status = ref('loading')
  const errorMessage = ref('')
  const selected = ref(null)
  const mode = ref(MODES.LIST)
  const busy = ref('')
  const form = useForm({ validationSchema: computed(() => legalDraftSchema(t)) })

  const draft = computed(() => versions.value.find((item) => item.status === DRAFT) ?? null)
  const current = computed(() => versions.value.find((item) => item.status === CURRENT) ?? null)

  async function loadList() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      versions.value = (await legalVersionsService.list(kind.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function edit(version, nextMode = MODES.EDIT) {
    selected.value = version
    form.resetForm({ values: legalDraftValues(version) })
    mode.value = nextMode
  }

  async function open(version) {
    busy.value = 'open'
    try {
      const detail = (await legalVersionsService.get(version.id)).data
      if (detail.status === DRAFT) edit(detail)
      else {
        selected.value = detail
        mode.value = MODES.VIEW
      }
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      busy.value = ''
    }
  }

  async function startDraft() {
    const source = current.value ? (await legalVersionsService.get(current.value.id)).data : null
    edit(
      { ...legalDraftValues(source), version: suggestedVersionLabel(), status: DRAFT },
      MODES.NEW,
    )
  }

  function close() {
    selected.value = null
    mode.value = MODES.LIST
  }

  async function rejected(error) {
    form.setErrors(firstFieldErrors(error))
    toast.error(error?.normalized?.message || t('errors.unexpected'))
    if (error?.normalized?.status === CONFLICT) {
      close()
      await loadList()
    }
  }

  async function run(name, execute) {
    if (busy.value) return
    busy.value = name
    try {
      await execute()
    } catch (error) {
      await rejected(error)
    } finally {
      busy.value = ''
    }
  }

  const save = form.handleSubmit((values) =>
    run('save', async () => {
      const saved =
        mode.value === MODES.NEW
          ? await legalVersionsService.createDraft(kind.value, legalDraftPayload(values))
          : await saveChanges(values)
      if (!saved) return
      edit(saved.data)
      toast.success(t('legalAdmin.toasts.saved'))
      await loadList()
    }),
  )

  async function saveChanges(values) {
    const changes = changedLegalDraft(selected.value, values)
    if (!Object.keys(changes).length) {
      toast.info(t('legalAdmin.toasts.unchanged'))
      return null
    }
    return legalVersionsService.updateDraft(selected.value.id, changes)
  }

  async function discard() {
    const accepted = await confirm({
      title: t('legalAdmin.discardTitle'),
      message: t('legalAdmin.discardMessage'),
      confirmLabel: t('legalAdmin.discard'),
      variant: 'danger',
    })
    if (!accepted) return
    await run('discard', async () => {
      await legalVersionsService.discardDraft(selected.value.id)
      toast.success(t('legalAdmin.toasts.discarded'))
      close()
      await loadList()
    })
  }

  async function publish() {
    if (form.meta.value.dirty) return toast.error(t('legalAdmin.toasts.saveFirst'))
    const accepted = await confirm({
      title: t('legalAdmin.publishTitle'),
      message: t('legalAdmin.publishMessage', { version: selected.value.version }),
      confirmLabel: t('legalAdmin.publish'),
    })
    if (!accepted) return undefined
    const extra = await reasonExtra()
    if (!extra) return undefined
    return run('publish', async () => {
      const published = (await legalVersionsService.publish(selected.value.id, extra)).data
      selected.value = published
      mode.value = MODES.VIEW
      toast.success(t('legalAdmin.toasts.published'))
      await loadList()
    })
  }

  return {
    MODES,
    versions,
    status,
    errorMessage,
    selected,
    mode,
    busy,
    form,
    draft,
    current,
    reasonPrompt,
    loadList,
    open,
    startDraft,
    close,
    save,
    discard,
    publish,
  }
}
