<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileFieldInput from './ProfileFieldInput.vue'
import ProfileFieldCell from './ProfileFieldCell.vue'
import ProfileFormNotice from './ProfileFormNotice.vue'
import ProfileSectionFooter from './ProfileSectionFooter.vue'
import { useProfileSectionForm } from '@/composables/useProfileSectionForm'
import { useProfileFieldDisplay } from '@/composables/useProfileFieldDisplay'
import { readPath } from '@/utils/entityProfile/paths'
import { fieldLabelKey, nextMissingAfter } from '@/utils/entityProfile/sections'
import { PROFILE_READ_ONLY_FIELDS, PROFILE_SAVE_STATES as S } from '@/constants/entityProfile'

const props = defineProps({
  section: { type: Object, required: true },
  profile: { type: Object, required: true },
  focusField: { type: String, default: null },
  regionOptions: { type: Array, default: () => [] },
  cityOptionsFor: { type: Function, required: true },
})

const emit = defineEmits(['saved', 'cancel', 'dirty'])
const { t } = useI18n()
const root = ref(null)
const lastField = ref(props.focusField)
const display = useProfileFieldDisplay()
const { form, status, isDirty, errorFields, submit } = useProfileSectionForm(
  toRef(props, 'section'),
  { onSaved: () => emit('saved') },
)

const cityOptions = computed(() => props.cityOptionsFor(form.values.regionId))
const blankMissing = computed(() =>
  props.section.fields
    .filter((field) => field.counted && !PROFILE_READ_ONLY_FIELDS.includes(field.name))
    .map((field) => field.name)
    .filter((name) => String(readPath(form.values, name) ?? '').trim() === ''),
)

function isReadOnly(field) {
  return PROFILE_READ_ONLY_FIELDS.includes(field.name)
}

function focusOn(name) {
  const target = name && root.value?.querySelector(`[name="${name}"]`)
  const fallback = root.value?.querySelector('input, button[role="combobox"], textarea')
  ;(target || fallback)?.focus()
}

function rememberField(event) {
  const name = event.target?.getAttribute?.('name')
  if (name && props.section.fields.some((field) => field.name === name)) lastField.value = name
}

function focusNextMissing() {
  focusOn(nextMissingAfter(blankMissing.value, lastField.value))
}

watch(isDirty, (value) => emit('dirty', value), { immediate: true })
watch(
  () => form.values.cityId && form.values.regionId,
  () => {
    const cityId = String(form.values.cityId ?? '')
    if (cityId && !cityOptions.value.some((option) => option.value === cityId))
      form.setFieldValue('cityId', '')
  },
)
watch(
  () => props.focusField,
  (name) => nextTick(() => focusOn(name)),
)
onMounted(() => nextTick(() => focusOn(props.focusField)))
</script>

<template>
  <form
    ref="root"
    class="profile-section__form"
    novalidate
    :aria-busy="status === S.SAVING"
    @focusin="rememberField"
    @submit.prevent="submit"
  >
    <button
      v-if="blankMissing.length"
      type="button"
      class="profile-section__next-missing"
      @click="focusNextMissing"
    >
      {{ t('portal.profile.actions.nextMissing') }}
    </button>
    <ProfileFormNotice :status="status" :error-fields="errorFields" />
    <fieldset class="profile-section__fields" :disabled="status === S.SAVING">
      <legend class="profile-section__legend">
        {{ t(`portal.profile.sections.${section.key}`) }}
      </legend>
      <template v-for="field in section.fields" :key="field.name">
        <dl v-if="isReadOnly(field)" class="profile-section__readonly">
          <ProfileFieldCell
            :label="t(fieldLabelKey(field.name))"
            :value="display.valueOf(profile, field.name)"
            :ltr="display.isLtr(field.name)"
          />
        </dl>
        <ProfileFieldInput
          v-else
          :field="field"
          :region-options="regionOptions"
          :city-options="cityOptions"
        />
      </template>
    </fieldset>
    <ProfileSectionFooter :status="status" :dirty="isDirty" editing>
      <BaseButton variant="ghost" :disabled="status === S.SAVING" @click="emit('cancel')">
        {{ t('portal.profile.actions.cancel') }}
      </BaseButton>
      <BaseButton type="submit" variant="primary" icon="check" :loading="status === S.SAVING">
        {{
          status === S.SAVING
            ? t('portal.profile.actions.saving')
            : t('portal.profile.actions.save')
        }}
      </BaseButton>
    </ProfileSectionFooter>
  </form>
</template>
