<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { BOARD_QUERY_KEY } from '@/constants/dashboardBoards'

defineProps({
  boards: { type: Array, required: true },
  current: { type: String, required: true },
})

const { t } = useI18n()
const route = useRoute()
</script>

<template>
  <nav class="dashboard-board-nav" :aria-label="t('dashboard.boardsLabel')">
    <ul class="dashboard-board-nav__list">
      <li v-for="board in boards" :key="board">
        <RouterLink
          class="dashboard-board-nav__link"
          :class="{ 'dashboard-board-nav__link--current': board === current }"
          :aria-current="board === current ? 'page' : undefined"
          :to="{ name: route.name, query: { ...route.query, [BOARD_QUERY_KEY]: board } }"
          >{{ t(`dashboard.boards.${board}`) }}</RouterLink
        >
      </li>
    </ul>
  </nav>
</template>
