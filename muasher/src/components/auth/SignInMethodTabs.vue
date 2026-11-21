<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { SIGN_IN_METHOD_VALUES } from '@/constants/signIn'
import { isRightToLeft, tabIndexAfterKey } from '@/utils/tabNavigation'

const props = defineProps({
  modelValue: {
    type: String,
    required: true,
    validator: (value) => SIGN_IN_METHOD_VALUES.includes(value),
  },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const panelId = useId()
const tabs = []

function tabIdOf(method) {
  return `${panelId}-tab-${method}`
}

function select(index) {
  emit('update:modelValue', SIGN_IN_METHOD_VALUES[index])
  tabs[index]?.focus()
}

function onKeydown(event, index) {
  const next = tabIndexAfterKey(event.key, index, SIGN_IN_METHOD_VALUES.length, isRightToLeft())
  if (next === null) return
  event.preventDefault()
  select(next)
}
</script>

<template>
  <div class="sign-in-methods">
    <div class="sign-in-methods__list" role="tablist" :aria-label="t('auth.login.methods.label')">
      <button
        v-for="(method, index) in SIGN_IN_METHOD_VALUES"
        :id="tabIdOf(method)"
        :key="method"
        :ref="(element) => (tabs[index] = element)"
        type="button"
        role="tab"
        class="sign-in-methods__tab"
        :class="{ 'is-selected': method === props.modelValue }"
        :data-method="method"
        :aria-selected="method === props.modelValue"
        :aria-controls="panelId"
        :tabindex="method === props.modelValue ? 0 : -1"
        @click="select(index)"
        @keydown="onKeydown($event, index)"
      >
        {{ t(`auth.login.methods.${method}`) }}
      </button>
    </div>
    <div
      :id="panelId"
      class="sign-in-methods__panel"
      role="tabpanel"
      :aria-labelledby="tabIdOf(props.modelValue)"
    >
      <slot />
    </div>
  </div>
</template>
