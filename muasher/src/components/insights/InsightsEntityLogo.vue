<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  url: { type: String, default: null },
  name: { type: String, required: true },
})

const { t } = useI18n()
const failed = ref(false)

watch(
  () => props.url,
  () => {
    failed.value = false
  },
)
</script>

<template>
  <span class="executive-summary__logo" data-testid="executive-logo">
    <img
      v-if="url && !failed"
      class="executive-summary__logo-image"
      :src="url"
      :alt="t('insights.board.logoAlt', { name })"
      @error="failed = true"
    />
    <AppIcon v-else name="building" aria-hidden="true" />
  </span>
</template>
