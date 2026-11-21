<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ResultsRowTable from './ResultsRowTable.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  region: { type: Object, required: true },
  rows: { type: Array, required: true },
  canRank: { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
  canUnapprove: { type: Boolean, default: false },
  busy: { type: String, default: '' },
})

const emit = defineEmits(['rank', 'approve', 'unapprove'])
const { t, locale } = useI18n()
</script>

<template>
  <Card class="region-results">
    <template #header>
      <h2 class="region-results__title">{{ localizedText(region.name, locale) }}</h2>
      <span class="region-results__badges">
        <Badge :variant="region.approved ? 'success' : 'warning'">{{
          region.approved ? t('award.results.regionApproved') : t('award.results.regionOpen')
        }}</Badge>
        <Badge v-if="region.pending" variant="muted">{{
          t('award.results.pending', { count: region.pending }, region.pending)
        }}</Badge>
        <Badge variant="info">{{
          t('award.results.ranked', { ranked: region.ranked, total: region.total })
        }}</Badge>
      </span>
    </template>
    <ResultsRowTable
      :rows="rows"
      rank-field="regionalRank"
      :can-rank="canRank && !region.approved"
      :busy="busy"
      @rank="(row, value) => emit('rank', row, value)"
    />
    <div class="region-results__actions">
      <BaseButton
        v-if="canApprove && !region.approved"
        variant="primary"
        icon="stamp"
        :loading="busy === `region-${region.id}`"
        :disabled="region.pending > 0 || !region.ranked || Boolean(busy)"
        @click="emit('approve', region)"
      >
        {{ t('award.results.approveRegion') }}
      </BaseButton>
      <BaseButton
        v-if="canUnapprove && region.approved"
        variant="danger"
        icon="lock-open"
        @click="emit('unapprove', region)"
        >{{ t('award.results.unapproveRegion') }}</BaseButton
      >
      <p v-if="!region.approved && region.pending" class="cycle-actions__note">
        {{ t('award.results.pendingHint') }}
      </p>
    </div>
  </Card>
</template>
