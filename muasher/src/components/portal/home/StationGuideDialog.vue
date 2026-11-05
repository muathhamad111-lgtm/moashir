<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { usePathGuide } from '@/composables/usePathGuide'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  path: { type: String, default: 'accredited' },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { status, intro, steps, afterSubmission, load } = usePathGuide(computed(() => props.path))
watch(
  () => props.modelValue,
  (value) => {
    if (value && status.value !== 'success') load()
  },
  { immediate: true },
)

function update(value) {
  emit('update:modelValue', value)
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('portal.home.guide.title')"
    @update:model-value="update"
  >
    <div class="station-guide">
      <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
      <ErrorState v-else-if="status === 'error'" @retry="load" />
      <template v-else>
        <p v-if="intro" class="station-guide__intro">{{ intro }}</p>
        <ol v-if="steps.length" class="station-guide__steps">
          <li v-for="step in steps" :key="step" class="station-guide__step">{{ step }}</li>
        </ol>
        <p v-else class="station-guide__empty">{{ t('portal.home.guide.empty') }}</p>
        <p v-if="afterSubmission" class="station-guide__after">
          <AppIcon name="clock" />
          <span>{{ afterSubmission }}</span>
        </p>
      </template>
    </div>
  </BaseModal>
</template>
