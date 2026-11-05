<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { withLatinNumerals } from '@/utils/numerals'

defineProps({
  rows: { type: Array, required: true },
})

const { t, locale } = useI18n()
const numbers = computed(() => new Intl.NumberFormat(withLatinNumerals(locale.value)))
</script>

<template>
  <div class="table-wrap">
    <table class="table purpose-breakdown">
      <caption class="purpose-breakdown__caption">
        {{
          t('finance.overview.byPurpose')
        }}
      </caption>
      <thead class="table__head">
        <tr>
          <th class="table__cell" scope="col">{{ t('finance.fields.service') }}</th>
          <th class="table__cell" scope="col">{{ t('finance.stats.completed') }}</th>
          <th class="table__cell" scope="col">{{ t('finance.stats.incomplete') }}</th>
          <th class="table__cell" scope="col">{{ t('finance.stats.totalIncome') }}</th>
          <th class="table__cell" scope="col">{{ t('finance.stats.totalVat') }}</th>
          <th class="table__cell" scope="col">{{ t('finance.stats.totalRefunded') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.purpose" class="table__row">
          <th class="table__cell" scope="row">{{ t(`finance.purposes.${row.purpose}`) }}</th>
          <td class="table__cell">{{ numbers.format(row.completedCount) }}</td>
          <td class="table__cell">{{ numbers.format(row.incompleteCount) }}</td>
          <td class="table__cell">
            <MoneyAmount :value="row.totalIncome" :currency="row.currency" strong />
          </td>
          <td class="table__cell">
            <MoneyAmount :value="row.totalVat" :currency="row.currency" />
          </td>
          <td class="table__cell">
            <MoneyAmount :value="row.totalRefunded" :currency="row.currency" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
