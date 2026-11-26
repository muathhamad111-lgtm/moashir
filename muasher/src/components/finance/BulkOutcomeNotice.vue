<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { SKIPPED_CODES } from '@/constants/bankTransfer'

const props = defineProps({
  outcome: { type: Object, required: true },
  references: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['dismiss'])
const { t } = useI18n()

const skipped = computed(() =>
  props.outcome.skipped.map((item) => ({
    id: item.id,
    reference: props.references[item.id] ?? '',
    reason: t(
      `finance.transfers.bulk.skipped.${SKIPPED_CODES.includes(item.code) ? item.code.replace('.', '_') : 'other'}`,
    ),
  })),
)
</script>

<template>
  <div class="bulk-outcome" role="status">
    <p class="bulk-outcome__line bulk-outcome__line--success">
      <AppIcon name="check-circle" />
      {{
        t('finance.transfers.bulk.outcome', {
          decided: outcome.decided.length,
          skipped: outcome.skipped.length,
        })
      }}
    </p>
    <p
      v-for="item in skipped"
      :key="item.id"
      class="bulk-outcome__line bulk-outcome__line--warning"
    >
      <AppIcon name="lock" />
      <span>
        <bdi dir="ltr">{{ item.reference }}</bdi>
        {{ item.reason }}
      </span>
    </p>
    <button type="button" class="bulk-outcome__dismiss" @click="emit('dismiss')">
      {{ t('actions.close') }}
    </button>
  </div>
</template>
