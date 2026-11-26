<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'

// Who paid and for what — the receipt's opening lines (the entity's e-mail is not needed here).
const props = defineProps({
  transaction: { type: Object, required: true },
})

const { t, locale } = useI18n()

const entityName = computed(
  () => props.transaction.entity.name?.[locale.value] || props.transaction.entity.name?.ar || '',
)
</script>

<template>
  <dl class="payment-facts transaction-parties">
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.service') }}</dt>
      <dd>{{ t(`finance.purposes.${transaction.purpose}`) }}</dd>
    </div>
    <div v-if="transaction.awardCycle" class="payment-facts__row" data-testid="payment-award-cycle">
      <dt>{{ t('finance.fields.awardCycle') }}</dt>
      <dd>{{ localizedText(transaction.awardCycle.name, locale) }}</dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.entity') }}</dt>
      <dd>
        {{ entityName }}
        <bdi class="payment-facts__muted" dir="ltr">{{ transaction.entity.reference }}</bdi>
      </dd>
    </div>
  </dl>
</template>
