<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function update(next) {
  emit('update:modelValue', next)
}

function addAttachment() {
  update([...props.modelValue, { key: null, name: { ar: '', en: '' }, required: false }])
}

function removeAttachment(index) {
  update(props.modelValue.filter((_, i) => i !== index))
}

function setName(index, locale, value) {
  update(
    props.modelValue.map((item, i) =>
      i === index ? { ...item, name: { ...item.name, [locale]: value } } : item,
    ),
  )
}

function setRequired(index, value) {
  update(props.modelValue.map((item, i) => (i === index ? { ...item, required: value } : item)))
}
</script>

<template>
  <fieldset class="attachments-editor" :disabled="disabled">
    <legend class="attachments-editor__legend">{{ t('criteria.attachments.title') }}</legend>
    <p class="attachments-editor__hint">{{ t('criteria.attachments.hint') }}</p>

    <ul v-if="modelValue.length" class="attachments-editor__list">
      <li v-for="(item, index) in modelValue" :key="index" class="attachments-editor__row">
        <div class="attachments-editor__names">
          <label class="field">
            <span class="field__label">{{ t('criteria.attachments.nameAr') }}</span>
            <input
              class="field__control"
              type="text"
              dir="rtl"
              :value="item.name.ar"
              :placeholder="t('criteria.attachments.nameArPlaceholder')"
              @input="setName(index, 'ar', $event.target.value)"
            />
          </label>
          <label class="field">
            <span class="field__label">{{ t('criteria.attachments.nameEn') }}</span>
            <input
              class="field__control"
              type="text"
              dir="ltr"
              :value="item.name.en"
              :placeholder="t('criteria.attachments.nameEnPlaceholder')"
              @input="setName(index, 'en', $event.target.value)"
            />
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
              <i
                v-if="item.required"
                class="fa-solid fa-check checkbox__check"
                aria-hidden="true"
              ></i>
            </span>
            <span class="checkbox__label">{{ t('criteria.attachments.required') }}</span>
          </label>
          <BaseButton
            variant="ghost"
            size="sm"
            icon-only
            icon="fa-solid fa-trash-can"
            :aria-label="t('criteria.attachments.remove')"
            @click="removeAttachment(index)"
          />
        </div>
      </li>
    </ul>
    <p v-else class="attachments-editor__empty">{{ t('criteria.attachments.empty') }}</p>

    <BaseButton variant="secondary" size="sm" icon="fa-solid fa-plus" @click="addAttachment">
      {{ t('criteria.attachments.add') }}
    </BaseButton>
  </fieldset>
</template>
