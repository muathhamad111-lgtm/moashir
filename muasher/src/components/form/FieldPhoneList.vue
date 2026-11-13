<script setup>
import { computed, ref, toRef, useId } from 'vue'
import { useField, useFormErrors } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { SECRET_PATTERNS } from '@/constants/integrations'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  max: { type: Number, required: true },
  hint: { type: String, default: '' },
})

const { t } = useI18n()
const { value, errorMessage } = useField(toRef(props, 'name'))
const formErrors = useFormErrors()
const draft = ref('')
const draftError = ref('')
const inputId = useId()

const numbers = computed(() => (Array.isArray(value.value) ? value.value : []))
const full = computed(() => numbers.value.length >= props.max)
const itemErrors = computed(() =>
  numbers.value.map((_, index) => formErrors.value[`${props.name}[${index}]`] ?? ''),
)
const shownError = computed(
  () => draftError.value || errorMessage.value || itemErrors.value.find(Boolean) || '',
)

function add() {
  const number = draft.value.trim()
  if (!SECRET_PATTERNS.SAUDI_MOBILE.test(number)) {
    draftError.value = t('integrations.validation.saudiMobile')
    return
  }
  draftError.value = ''
  if (!numbers.value.includes(number)) value.value = [...numbers.value, number]
  draft.value = ''
}

function remove(number) {
  value.value = numbers.value.filter((item) => item !== number)
}
</script>

<template>
  <fieldset class="phone-list" :class="{ 'field--error': shownError }">
    <legend class="phone-list__legend">{{ label }}</legend>
    <ul v-if="numbers.length" class="phone-list__items">
      <li
        v-for="(number, index) in numbers"
        :key="number"
        class="phone-list__item"
        :class="{ 'is-invalid': itemErrors[index] }"
      >
        <span dir="ltr">{{ number }}</span>
        <BaseButton
          variant="ghost"
          size="sm"
          icon-only
          icon="x"
          :aria-label="t('integrations.sms.removeNumber', { number })"
          @click="remove(number)"
        />
      </li>
    </ul>
    <p v-else class="phone-list__empty">{{ t('integrations.sms.noTestNumbers') }}</p>
    <div class="phone-list__add">
      <label class="phone-list__label" :for="inputId">{{ t('integrations.sms.newNumber') }}</label>
      <input
        :id="inputId"
        v-model="draft"
        class="field__control phone-list__input"
        type="tel"
        inputmode="tel"
        autocomplete="off"
        dir="ltr"
        :placeholder="t('integrations.sms.numberPlaceholder')"
        :disabled="full"
        :aria-describedby="`${inputId}-hint`"
        @keydown.enter.prevent="add"
      />
      <BaseButton variant="secondary" size="sm" icon="plus" :disabled="full" @click="add">
        {{ t('integrations.sms.addNumber') }}
      </BaseButton>
    </div>
    <p :id="`${inputId}-hint`" class="field__hint">
      {{ full ? t('integrations.sms.numbersFull', { max }) : hint }}
    </p>
    <p v-if="shownError" class="field__error" role="alert">{{ shownError }}</p>
  </fieldset>
</template>
