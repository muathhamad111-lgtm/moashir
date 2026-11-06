import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'
import { useEntityStore } from '@/stores/entity'
import { ownProfileSchema } from '@/utils/entitySchemas'
import { ownProfileFormValues, ownProfilePayload } from '@/utils/entityPayload'
import { riyadhDateKey } from '@/utils/datetime'
import { PROFILE_TABS, PROFILE_TAB_FIELDS } from '@/constants/entityProfile'

function pick(values, fields) {
  return Object.fromEntries(fields.map((field) => [field, values[field]]))
}

function sameValue(left, right) {
  return JSON.stringify(left ?? '') === JSON.stringify(right ?? '')
}

export function useEntityProfileForm(tab) {
  const { t } = useI18n()
  const toast = useToast()
  const entityStore = useEntityStore()
  const saved = ref(ownProfileFormValues(entityStore.profile ?? {}))
  const today = riyadhDateKey(new Date())
  let savingOwnChanges = false

  const form = useValidatedForm({
    schema: computed(() =>
      ownProfileSchema(t, today, { unifiedNumberRequired: tab.value === PROFILE_TABS.BASIC }),
    ),
    initialValues: { ...saved.value },
    action: 'entities.updateMine.submit',
    submit: (values) => save(values),
    keepValuesOnUnmount: true,
  })

  const dirtyFields = computed(() =>
    PROFILE_TAB_FIELDS[tab.value].filter(
      (field) => !sameValue(form.values[field], saved.value[field]),
    ),
  )
  const changedFields = computed(() =>
    Object.keys(saved.value).filter((field) => !sameValue(form.values[field], saved.value[field])),
  )
  const isDirty = computed(() => changedFields.value.length > 0)

  async function save(values) {
    const merged = { ...saved.value, ...pick(values, PROFILE_TAB_FIELDS[tab.value]) }
    savingOwnChanges = true
    try {
      await entityStore.updateProfile(ownProfilePayload(merged))
    } catch (error) {
      savingOwnChanges = false
      throw error
    }
    toast.success(t('portal.profile.saved'))
  }

  function reset() {
    form.resetForm({ values: { ...saved.value } })
  }

  watch(
    () => entityStore.profile,
    (profile) => {
      if (!profile) return
      const kept = savingOwnChanges ? {} : pick(form.values, changedFields.value)
      savingOwnChanges = false
      saved.value = ownProfileFormValues(profile)
      form.resetForm({ values: { ...saved.value, ...kept } })
    },
  )

  watch(
    () => form.values.foundedOn,
    (value) => {
      const year = /^(\d{4})-\d{2}-\d{2}$/.exec(value ?? '')?.[1]
      if (year && form.values.foundingYear !== year) form.setFieldValue('foundingYear', year)
    },
  )

  return { form, isDirty, dirtyFields, reset }
}
