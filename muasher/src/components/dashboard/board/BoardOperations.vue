<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import OverviewPanel from '../overview/OverviewPanel.vue'
import OverviewCriteriaBars from '../overview/OverviewCriteriaBars.vue'
import BoardRow from './BoardRow.vue'
import BoardFacts from './BoardFacts.vue'
import BoardTargets from './BoardTargets.vue'
import BoardStorage from './BoardStorage.vue'
import { useOverviewCharts } from '@/composables/useOverviewCharts'
import { useBoardBreakdowns } from '@/composables/useBoardBreakdowns'

// Operations rows: the weakest criteria (2/3) beside the targets (1/3); finance, award and inbox as
// three key-value cards; last, the platform-level cards: users (1/3) and storage (2/3, large gauge).
// Users come first in the markup so storage is the very last card on phones too.
const props = defineProps({
  overview: { type: Object, required: true },
})

const { t } = useI18n()
const overview = toRef(props, 'overview')
const charts = useOverviewCharts(overview)
const board = useBoardBreakdowns(overview)
const T = (key) => t(`dashboard.board.titles.${key}`)
const scope = (key) => t(`dashboard.board.scope.${key}`)
</script>

<template>
  <BoardRow label="quality">
    <OverviewPanel
      data-panel="criteria"
      :title="T('criteria')"
      icon="warning-triangle"
      :note="scope('period')"
      style="--board-span: 2"
    >
      <OverviewCriteriaBars :data="charts.widget('lowest_criteria').data" />
    </OverviewPanel>
    <OverviewPanel data-panel="targets" :title="T('targets')" icon="target" :note="scope('period')">
      <BoardTargets :rows="board.targets.value" :caption="T('targets')" />
    </OverviewPanel>
  </BoardRow>

  <BoardRow label="facts">
    <OverviewPanel data-panel="finance" :title="T('finance')" icon="sar" :note="scope('period')">
      <BoardFacts :rows="board.finance.value" :caption="T('finance')" />
    </OverviewPanel>
    <OverviewPanel data-panel="award" :title="T('award')" icon="trophy" :note="scope('award')">
      <BoardFacts :rows="board.award.value" :caption="T('award')" />
    </OverviewPanel>
    <OverviewPanel data-panel="inbox" :title="T('inbox')" icon="envelope" :note="scope('period')">
      <BoardFacts :rows="board.inbox.value" :caption="T('inbox')" />
    </OverviewPanel>
  </BoardRow>

  <BoardRow label="platform">
    <OverviewPanel data-panel="users" :title="T('users')" icon="user-check" :note="scope('users')">
      <BoardFacts :rows="board.users.value" :caption="T('users')" />
    </OverviewPanel>
    <OverviewPanel
      v-if="board.storage.value"
      class="board-panel--lg"
      data-panel="storage"
      :title="T('storage')"
      icon="folder"
      :note="scope('storage')"
      style="--board-span: 2"
    >
      <BoardStorage :storage="board.storage.value" />
    </OverviewPanel>
  </BoardRow>
</template>
