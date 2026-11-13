<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { PRICE_STATE_VARIANTS } from '@/constants/prices'
import { formatRiyadhDate, formatRiyadhDateTime } from '@/utils/datetime'

defineProps({
  rowKey: { type: String, required: true },
  status: { type: String, required: true },
  items: { type: Array, required: true },
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
})

const emit = defineEmits(['page', 'retry'])
const { t, locale } = useI18n()
</script>

<template>
  <section
    class="path-price-history"
    :aria-labelledby="`price-history-${rowKey}`"
    :data-testid="`price-history-${rowKey}`"
  >
    <h3 :id="`price-history-${rowKey}`" class="path-price-history__title">
      {{ t('settings.prices.history.title') }}
    </h3>
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status === 'error'" @retry="emit('retry')" />
    <p v-else-if="status === 'empty'" class="path-price-history__empty">
      {{ t('settings.prices.history.empty') }}
    </p>
    <div v-else class="table-wrap">
      <table class="table path-price-history__table">
        <thead class="table__head">
          <tr>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.when') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.who') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.before') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.after') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.effective') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.prices.history.state') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="table__row">
            <td class="table__cell">{{ formatRiyadhDateTime(item.createdAt, locale) }}</td>
            <td class="table__cell">{{ item.setBy?.name ?? t('settings.prices.system') }}</td>
            <td class="table__cell">
              <template v-if="item.before">
                <MoneyAmount :value="item.before.priceBeforeVat" :currency="item.currency" />
                <span class="path-price-history__sub">
                  {{ t(`settings.prices.availability.${item.before.availability}`) }}
                </span>
              </template>
              <span v-else aria-hidden="true">—</span>
            </td>
            <td class="table__cell">
              <MoneyAmount :value="item.priceBeforeVat" :currency="item.currency" strong />
              <span class="path-price-history__sub">
                {{ t(`settings.prices.availability.${item.availability}`) }}
              </span>
            </td>
            <td class="table__cell">{{ formatRiyadhDate(item.effectiveFrom, locale) }}</td>
            <td class="table__cell">
              <Badge :variant="PRICE_STATE_VARIANTS[item.state] ?? 'muted'">
                {{ t(`settings.prices.history.states.${item.state}`) }}
              </Badge>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination
      v-if="lastPage > 1"
      :page="page"
      :last-page="lastPage"
      @update:page="emit('page', $event)"
    />
  </section>
</template>
