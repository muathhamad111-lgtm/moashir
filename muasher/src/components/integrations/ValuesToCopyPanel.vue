<script setup>
import { useI18n } from 'vue-i18n'
import CopyValueRow from './CopyValueRow.vue'
import { useClipboard } from '@/composables/useClipboard'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  items: { type: Array, required: true },
})

const { t } = useI18n()
const { copiedKey, failedKey, copy } = useClipboard()
</script>

<template>
  <section class="values-to-copy" :aria-label="t('integrations.copy.title')">
    <h3 class="values-to-copy__title">
      <AppIcon name="doc" />
      {{ t('integrations.copy.title') }}
    </h3>
    <p class="values-to-copy__hint">{{ t('integrations.copy.hint') }}</p>
    <dl class="values-to-copy__list">
      <CopyValueRow
        v-for="item in items"
        :key="item.key"
        :label="item.label"
        :value="item.value"
        :copied="copiedKey === item.key"
        :failed="failedKey === item.key"
        @copy="copy(item.key, item.value)"
      />
    </dl>
  </section>
</template>
