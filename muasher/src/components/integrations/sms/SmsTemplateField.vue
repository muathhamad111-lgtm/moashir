<script setup>
import { computed } from 'vue'
import { useField, useFieldError } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import TemplateEditor from '@/components/settings/TemplateEditor.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SmsSegmentCounter from './SmsSegmentCounter.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  index: { type: Number, required: true },
  definition: { type: Object, required: true },
  approvedBodies: { type: Array, default: null },
})

const { t, locale } = useI18n()
const path = computed(() => `templates[${props.index}].body`)
const { value, setValue } = useField(path)
const arError = useFieldError(computed(() => `${path.value}.ar`))
const enError = useFieldError(computed(() => `${path.value}.en`))
const errors = computed(() => ({ ar: arError.value ?? '', en: enError.value ?? '' }))
const label = computed(() => localizedText(props.definition.label, locale.value))
const placeholders = computed(() =>
  props.definition.placeholders.map((name) => ({
    token: `{${name}}`,
    label: { [locale.value]: t(`integrations.sms.placeholders.${name}`) },
  })),
)
const requiredTokens = computed(() =>
  props.definition.requiredPlaceholders.map((name) => `{${name}}`).join(' '),
)
const restoreDefault = () => setValue({ ...props.definition.defaultBody })
</script>

<template>
  <div class="sms-template">
    <TemplateEditor
      v-model="value"
      :name="`sms-template-${definition.key}`"
      :label="label"
      :placeholders="placeholders"
      :rows="3"
      :error="errors"
    >
      <template #field-footer="{ value: text }">
        <SmsSegmentCounter
          :text="text"
          :single-segment="definition.singleSegment"
          :approved-bodies="approvedBodies"
        />
      </template>
    </TemplateEditor>
    <div class="sms-template__footer">
      <p class="field__hint">
        {{ t('integrations.sms.requiredPlaceholders', { tokens: requiredTokens }) }}
      </p>
      <BaseButton variant="ghost" size="sm" icon="fa-solid fa-rotate-left" @click="restoreDefault">
        {{ t('integrations.sms.restoreDefault') }}
      </BaseButton>
    </div>
  </div>
</template>
