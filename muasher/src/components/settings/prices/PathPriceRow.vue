<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PathPrice from '@/components/paths/PathPrice.vue'
import PathPriceFields from './PathPriceFields.vue'
import PathPriceVersion from './PathPriceVersion.vue'
import PathPriceHistory from './PathPriceHistory.vue'
import { usePathPriceRow } from '@/composables/usePathPriceRow'
import { usePriceHistory } from '@/composables/usePriceHistory'

const props = defineProps({
  row: { type: Object, required: true },
  limits: { type: Object, required: true },
  reasonExtra: { type: Function, required: true },
})

const emit = defineEmits(['saved'])
const { t } = useI18n()
const history = usePriceHistory(props.row.key)
const editor = usePathPriceRow(toRef(props, 'row'), {
  limits: toRef(props, 'limits'),
  reasonExtra: props.reasonExtra,
  onSaved: (row) => {
    emit('saved', row)
    history.refresh()
  },
})
const { saving, cancelling, stale } = editor
const historyId = `price-history-panel-${props.row.key}`
</script>

<template>
  <article
    class="path-price-row"
    :aria-labelledby="`price-row-title-${row.key}`"
    :data-testid="`price-row-${row.key}`"
  >
    <header class="path-price-row__head">
      <h2 :id="`price-row-title-${row.key}`" class="path-price-row__title">
        {{ t(`settings.prices.rows.${row.key}.title`) }}
      </h2>
      <PathPrice class="path-price-row__entity" :pricing="row.pricing" />
    </header>
    <p class="path-price-row__lead">{{ t(`settings.prices.rows.${row.key}.lead`) }}</p>
    <div class="path-price-row__versions">
      <PathPriceVersion
        :version="row.inForce"
        :title="t('settings.prices.inForce')"
        :test-id="`price-in-force-${row.key}`"
      />
      <PathPriceVersion
        v-if="row.scheduled"
        :version="row.scheduled"
        :title="t('settings.prices.scheduled')"
        :test-id="`price-scheduled-${row.key}`"
      >
        <BaseButton
          variant="ghost"
          size="sm"
          icon="x"
          :loading="cancelling"
          :data-testid="`price-cancel-${row.key}`"
          @click="editor.cancelScheduled"
        >
          {{ t('settings.prices.cancelScheduled') }}
        </BaseButton>
      </PathPriceVersion>
    </div>
    <p v-if="stale" class="settings-form__warning" role="alert" data-testid="price-stale">
      <AppIcon name="warning-triangle" />
      {{ t('settings.prices.stale') }}
    </p>
    <form class="settings-form path-price-row__form" novalidate @submit.prevent="editor.submit">
      <PathPriceFields :limits="limits" />
      <p v-if="row.scheduled" class="settings-form__note" role="note">
        <AppIcon name="info-circle" />
        {{ t('settings.prices.replacesScheduled') }}
      </p>
      <div class="path-price-row__actions">
        <button
          type="button"
          class="path-price-row__toggle"
          :aria-expanded="history.open.value"
          :aria-controls="historyId"
          :data-testid="`price-history-toggle-${row.key}`"
          @click="history.toggle"
        >
          <AppIcon name="clock" />
          {{ t('settings.prices.history.toggle', { count: row.historyTotal }) }}
        </button>
        <BaseButton type="submit" variant="primary" icon="save" :loading="saving">
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </form>
    <div v-if="history.open.value" :id="historyId">
      <PathPriceHistory
        :row-key="row.key"
        :status="history.status.value"
        :items="history.items.value"
        :page="history.page.value"
        :last-page="history.lastPage.value"
        @page="history.load"
        @retry="history.load(history.page.value)"
      />
    </div>
  </article>
</template>
