<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { showsDemoData } from '@/utils/mockPlan'

const props = defineProps({
  modules: { type: Array, default: () => [] },
  scope: {
    type: String,
    default: 'page',
    validator: (value) => ['page', 'section', 'actions'].includes(value),
  },
})

const { t } = useI18n()

const visible = computed(() => showsDemoData(props.modules))
const bodyKey = computed(() => `demoData.${props.scope}Body`)
</script>

<template>
  <p v-if="visible" class="demo-notice" :class="`demo-notice--${scope}`" role="note">
    <i class="fa-solid fa-flask-vial demo-notice__icon" aria-hidden="true"></i>
    <span>
      <strong class="demo-notice__title">{{ t('demoData.title') }}</strong>
      <span class="demo-notice__body">{{ t(bodyKey) }}</span>
    </span>
  </p>
</template>
