<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Tooltip from '@/components/ui/Tooltip.vue'

defineProps({
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()

function print() {
  window.print()
}
</script>

<template>
  <Tooltip class="overview-print" :text="t('dashboard.overview.print')">
    <template #default="{ describedBy }">
      <BaseButton
        class="executive-overview__print"
        variant="secondary"
        icon="printer"
        icon-only
        :aria-label="t('dashboard.overview.print')"
        :aria-describedby="describedBy"
        :disabled="disabled"
        @click="print"
      />
    </template>
  </Tooltip>
</template>
