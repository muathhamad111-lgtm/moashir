import { computed, ref, unref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { pricesSettingsService } from '@/services/pricesSettingsService'
import { useToast } from '@/composables/useToast'
import { PRICE_CODES } from '@/constants/prices'
import { logger } from '@/utils/logger'
import { errorCodeOf, financeErrorMessage, firstFieldErrors } from '@/utils/payments/financeErrors'
import { pathPricePayload, pathPriceSchema, pathPriceValues } from '@/utils/settings/pathPriceForm'

const ACTION = 'settings.prices.update'

export function usePathPriceRow(row, { limits, reasonExtra, onSaved }) {
  const i18n = useI18n()
  const { t } = i18n
  const toast = useToast()
  const saving = ref(false)
  const cancelling = ref(false)
  const stale = ref(false)
  const form = useForm({
    validationSchema: computed(() => pathPriceSchema(t, unref(limits))),
    initialValues: pathPriceValues(unref(row)),
  })

  watch(
    () => unref(row),
    (value) => form.resetForm({ values: pathPriceValues(value) }),
  )

  function rejected(error) {
    if (errorCodeOf(error) === PRICE_CODES.STALE_VERSION) {
      stale.value = true
      const current = error.normalized.meta?.row
      if (current) onSaved(current)
      return
    }
    form.setErrors(firstFieldErrors(error))
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  }

  async function send(values) {
    const extra = await reasonExtra()
    if (extra === null) return
    saving.value = true
    stale.value = false
    logger.breadcrumb('form', ACTION, { fields: Object.keys(values) })
    try {
      const payload = { ...pathPricePayload(values, unref(row)), ...extra }
      const { data, meta } = await pricesSettingsService.update(unref(row).key, payload)
      onSaved(data)
      toast.success(
        t(meta?.changed === false ? 'settings.prices.unchanged' : 'settings.prices.saved'),
      )
    } catch (error) {
      rejected(error)
    } finally {
      saving.value = false
    }
  }

  async function cancelScheduled() {
    const extra = await reasonExtra()
    if (extra === null) return
    cancelling.value = true
    logger.breadcrumb('form', 'settings.prices.cancelScheduled')
    try {
      onSaved((await pricesSettingsService.cancelScheduled(unref(row).key, extra)).data)
      toast.success(t('settings.prices.scheduledCancelled'))
    } catch (error) {
      toast.error(financeErrorMessage(i18n, error, 'errors.unexpected', 'settings.prices.errors'))
    } finally {
      cancelling.value = false
    }
  }

  return { form, saving, cancelling, stale, submit: form.handleSubmit(send), cancelScheduled }
}
