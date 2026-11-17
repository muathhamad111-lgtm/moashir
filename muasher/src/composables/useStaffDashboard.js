import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { dashboardService } from '@/services/dashboardService'
import { BOARD_QUERY_KEY, DASHBOARD_BOARDS } from '@/constants/dashboardBoards'

const BOARD_REFUSALS = [403, 422]

export function useStaffDashboard() {
  const route = useRoute()
  const router = useRouter()
  const summary = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')

  const requestedBoard = computed(() => {
    const value = route.query[BOARD_QUERY_KEY]
    return typeof value === 'string' && DASHBOARD_BOARDS.includes(value) ? value : null
  })

  function dropRequestedBoard() {
    const query = { ...route.query }
    delete query[BOARD_QUERY_KEY]
    return router.replace({ query })
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      summary.value = (await dashboardService.getBoard(requestedBoard.value)).data
      status.value = 'success'
    } catch (error) {
      const refused = BOARD_REFUSALS.includes(error?.normalized?.status)
      if (requestedBoard.value && refused) return dropRequestedBoard()
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  watch(requestedBoard, load)

  const board = computed(() => summary.value?.board ?? null)
  const boards = computed(() => summary.value?.availableBoards ?? [])

  return { summary, status, errorMessage, board, boards, load }
}
