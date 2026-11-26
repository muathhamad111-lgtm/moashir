<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from './BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

// A create form split into short steps in a side drawer: Next validates the step, the last step submits.
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, required: true },
  steps: { type: Array, required: true },
  step: { type: String, required: true },
  busy: { type: Boolean, default: false },
  submitLabel: { type: String, default: '' },
  // A rejection of the whole form (a 409, or no field named), shown above the buttons.
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'submit', 'next', 'back', 'close'])
const { t } = useI18n()

const at = computed(() => props.steps.findIndex((item) => item.value === props.step))
const isFirst = computed(() => at.value <= 0)
const isLast = computed(() => at.value === props.steps.length - 1)

// Enter on a field submits the form too: while a save is in flight nothing else is sent.
function onSubmit() {
  if (props.busy) return
  emit(isLast.value ? 'submit' : 'next')
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="title"
    layout="drawer"
    size="lg"
    :close-on-backdrop="!busy"
    @update:model-value="emit('update:modelValue', $event)"
    @close="emit('close')"
  >
    <ol class="form-steps__list" :aria-label="t('components.formSteps.label')">
      <li
        v-for="(item, index) in steps"
        :key="item.value"
        class="form-steps__step"
        :class="{ 'is-done': index < at, 'is-current': index === at }"
        :aria-current="index === at ? 'step' : undefined"
      >
        <span class="form-steps__bar" aria-hidden="true"></span>
        <span class="form-steps__label">
          <bdi>{{ index + 1 }}</bdi>
          {{ item.label }}
        </span>
      </li>
    </ol>
    <form
      class="form-steps__form"
      :aria-busy="busy || undefined"
      novalidate
      @submit.prevent="onSubmit"
    >
      <slot />
      <p v-if="error" class="form-steps__error" role="alert">
        <AppIcon name="info-circle" />
        <span>{{ error }}</span>
      </p>
      <footer class="modal__footer form-steps__footer">
        <BaseButton
          v-if="isFirst"
          variant="secondary"
          :disabled="busy"
          @click="emit('update:modelValue', false)"
        >
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton
          v-else
          variant="secondary"
          icon="arrow-left"
          :disabled="busy"
          @click="emit('back')"
        >
          {{ t('components.formSteps.back') }}
        </BaseButton>
        <span class="form-steps__spacer"></span>
        <BaseButton type="submit" variant="primary" :loading="busy">
          {{ isLast ? submitLabel || t('actions.save') : t('components.formSteps.next') }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>
