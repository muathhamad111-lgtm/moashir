<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import ActionCenter from './ActionCenter.vue'
import DashboardBoardNav from './DashboardBoardNav.vue'
import DashboardCards from './DashboardCards.vue'
import DashboardWidget from './DashboardWidget.vue'
import { useStaffDashboard } from '@/composables/useStaffDashboard'
import { EXECUTIVE_BOARD } from '@/constants/dashboardBoards'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  fixedBoard: { type: String, default: '' },
})

const { t, locale } = useI18n()
const { summary, status, errorMessage, board, boards, load } = useStaffDashboard({
  fixedBoard: props.fixedBoard || null,
})

const switchableBoards = computed(() => boards.value.filter((item) => item !== EXECUTIVE_BOARD))
const showsBoardNav = computed(
  () => !props.fixedBoard && switchableBoards.value.length > 1 && Boolean(board.value),
)
const boardTitle = computed(() => (board.value ? t(`dashboard.boards.${board.value}`) : ''))

const generatedAt = computed(() =>
  summary.value?.generatedAt ? formatRiyadhDateTime(summary.value.generatedAt, locale.value) : '',
)

onMounted(load)
</script>

<template>
  <div class="staff-dashboard">
    <DashboardBoardNav v-if="showsBoardNav" :boards="switchableBoards" :current="board" />
    <ActionCenter
      v-if="!fixedBoard"
      :items="summary?.actionCenter ?? []"
      :loading="status === 'loading'"
    />

    <div v-if="status === 'loading'" class="staff-dashboard__loading" aria-busy="true">
      <Skeleton variant="block" :count="2" />
    </div>
    <ErrorState
      v-else-if="status === 'error'"
      :message="errorMessage || t('dashboard.loadError')"
      @retry="load"
    />
    <section
      v-else-if="summary"
      class="staff-dashboard__board"
      :aria-labelledby="fixedBoard ? undefined : 'dashboard-board-title'"
      :aria-label="fixedBoard ? boardTitle : undefined"
      :data-board="board"
    >
      <h2 v-if="!fixedBoard" id="dashboard-board-title" class="staff-dashboard__board-title">
        {{ boardTitle }}
      </h2>
      <DashboardCards :cards="summary.cards" />
      <div class="staff-dashboard__widgets">
        <DashboardWidget
          v-for="widget in summary.widgets"
          :key="widget.key"
          :board="board"
          :widget="widget"
        />
      </div>
      <p v-if="generatedAt" class="staff-dashboard__generated">
        {{ t('dashboard.generatedAt') }}
        <time :datetime="summary.generatedAt">{{ generatedAt }}</time>
      </p>
    </section>
  </div>
</template>
