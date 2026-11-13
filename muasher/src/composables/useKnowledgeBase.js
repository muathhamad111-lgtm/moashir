import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { settingsService } from '@/services/settingsService'

const NEW_FILE_SIZE_KB = 500

export function useKnowledgeBase() {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const toast = useToast()
  const files = ref([])
  const status = ref('loading')
  const retraining = ref(false)

  async function load() {
    status.value = 'loading'
    try {
      files.value = (await settingsService.listKbFiles()).data ?? []
      status.value = files.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  async function runAndReload(task, successKey, failureKey) {
    try {
      await task()
      if (successKey) toast.success(t(successKey))
      await load()
    } catch (error) {
      toast.error(error?.normalized?.message || t(failureKey))
    }
  }

  const upload = () =>
    runAndReload(
      () =>
        settingsService.uploadKbFile({
          name: t('settings.ai.newFileName'),
          sizeKb: NEW_FILE_SIZE_KB,
        }),
      'settings.ai.uploadQueued',
      'settings.ai.uploadFailed',
    )

  const toggleApproved = (file) =>
    runAndReload(
      () => settingsService.toggleKbFileApproved(file.id, !file.approved),
      null,
      'settings.ai.approveFailed',
    )

  async function remove(file) {
    const accepted = await confirm({
      title: t('settings.ai.removeTitle'),
      message: file.name,
      confirmLabel: t('actions.delete'),
      variant: 'danger',
    })
    if (accepted)
      await runAndReload(
        () => settingsService.removeKbFile(file.id),
        'settings.ai.removed',
        'settings.ai.removeFailed',
      )
  }

  async function retrain() {
    const accepted = await confirm({
      title: t('settings.ai.retrainTitle'),
      confirmLabel: t('settings.ai.retrain'),
      variant: 'confirm',
    })
    if (!accepted) return
    retraining.value = true
    await runAndReload(
      () => settingsService.retrainKb(),
      'settings.ai.retrainQueued',
      'settings.ai.retrainFailed',
    )
    retraining.value = false
  }

  onMounted(load)

  return { files, status, retraining, load, upload, toggleApproved, remove, retrain }
}
