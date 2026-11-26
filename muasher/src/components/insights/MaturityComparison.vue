<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { isoDate } from '@/utils/datetime'

defineProps({
  previous: { type: Object, default: null },
  versionsDiffer: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <div class="maturity-comparison" role="note" data-testid="maturity-comparison">
    <AppIcon name="info-circle" class="maturity-comparison__icon" />
    <div>
      <i18n-t
        v-if="previous"
        keypath="insights.maturity.comparison"
        tag="p"
        class="maturity-comparison__title"
      >
        <template #number>
          <bdi dir="ltr">{{ previous.number }}</bdi>
        </template>
        <template #date>
          <bdi dir="ltr">{{ isoDate(previous.verifiedAt) }}</bdi>
        </template>
      </i18n-t>
      <p v-else class="maturity-comparison__title">{{ t('insights.delta.none') }}</p>
      <p v-if="versionsDiffer" class="maturity-comparison__text">
        {{ t('insights.delta.versionsDiffer') }}
      </p>
    </div>
  </div>
</template>
