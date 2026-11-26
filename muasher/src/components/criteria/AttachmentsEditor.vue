<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AttachmentRequirementDetails from './AttachmentRequirementDetails.vue'
import { keepRowKey, rowKeyOf } from '@/utils/rowKey'
import { attachmentRowErrors } from '@/utils/criteriaAttachments'

const NAME_FIELDS = [
  { locale: 'ar', dir: 'rtl', error: 'nameAr' },
  { locale: 'en', dir: 'ltr', error: 'nameEn' },
]

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const rowErrors = computed(() => attachmentRowErrors(props.errors, props.modelValue))

function errorIdOf(index, field) {
  return `attachment-${index}-${field}-error`
}

function update(next) {
  emit('update:modelValue', next)
}

function addAttachment() {
  update([
    ...props.modelValue,
    {
      key: null,
      name: { ar: '', en: '' },
      guidance: { ar: '', en: '' },
      required: false,
      allowedTypes: [],
      maxFiles: '',
    },
  ])
}

function removeAttachment(index) {
  update(props.modelValue.filter((_, i) => i !== index))
}

function replaceAt(index, change) {
  update(
    props.modelValue.map((item, i) =>
      i === index ? keepRowKey(item, { ...item, ...change(item) }) : item,
    ),
  )
}

function setName(index, locale, value) {
  replaceAt(index, (item) => ({ name: { ...item.name, [locale]: value } }))
}

function setRequired(index, value) {
  replaceAt(index, () => ({ required: value }))
}

function setDetails(index, change) {
  replaceAt(index, () => change)
}
</script>

<template>
  <fieldset class="attachments-editor" :disabled="disabled">
    <legend class="attachments-editor__legend">{{ t('criteria.attachments.title') }}</legend>
    <p class="attachments-editor__hint">{{ t('criteria.attachments.hint') }}</p>

    <ul v-if="modelValue.length" class="attachments-editor__list">
      <li v-for="(item, index) in modelValue" :key="rowKeyOf(item)" class="attachments-editor__row">
        <div class="attachments-editor__names">
          <label
            v-for="field in NAME_FIELDS"
            :key="field.locale"
            class="field"
            :class="{ 'field--error': rowErrors[index][field.error] }"
          >
            <span class="field__label">{{ t(`criteria.attachments.${field.error}`) }}</span>
            <input
              class="field__control"
              type="text"
              :dir="field.dir"
              :value="item.name[field.locale]"
              :placeholder="t(`criteria.attachments.${field.error}Placeholder`)"
              :aria-invalid="Boolean(rowErrors[index][field.error])"
              :aria-describedby="
                rowErrors[index][field.error] ? errorIdOf(index, field.error) : undefined
              "
              @input="setName(index, field.locale, $event.target.value)"
            />
            <p
              v-if="rowErrors[index][field.error]"
              :id="errorIdOf(index, field.error)"
              class="field__error"
              role="alert"
            >
              {{ rowErrors[index][field.error] }}
            </p>
          </label>
        </div>
        <div class="attachments-editor__controls">
          <label class="checkbox attachments-editor__required">
            <span class="checkbox__box">
              <input
                class="checkbox__input"
                type="checkbox"
                :checked="item.required"
                @change="setRequired(index, $event.target.checked)"
              />
              <AppIcon v-if="item.required" name="check" class="checkbox__check" />
            </span>
            <span class="checkbox__label">{{ t('criteria.attachments.required') }}</span>
          </label>
          <BaseButton
            variant="ghost"
            size="sm"
            icon-only
            icon="trash"
            :aria-label="t('criteria.attachments.remove')"
            @click="removeAttachment(index)"
          />
        </div>
        <AttachmentRequirementDetails
          :item="item"
          :index="index"
          :errors="rowErrors[index]"
          @change="setDetails(index, $event)"
        />
        <p v-if="rowErrors[index].row" class="field__error attachments-editor__error" role="alert">
          {{ rowErrors[index].row }}
        </p>
      </li>
    </ul>
    <p v-else class="attachments-editor__empty">{{ t('criteria.attachments.empty') }}</p>

    <BaseButton variant="secondary" size="sm" icon="plus" @click="addAttachment">
      {{ t('criteria.attachments.add') }}
    </BaseButton>
  </fieldset>
</template>
