<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { useProfileChanges } from '@/composables/useProfileChanges'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { changeItemLabelKey } from '@/utils/entityProfile/changeItems'
import { formatDate } from '@/utils/datetime'

const { t, locale } = useI18n()
const { changes, status, errorMessage, pagination, goTo } = useProfileChanges((params) =>
  profileChangeRequestsService.ownList(params),
)

function itemsOf(request) {
  return request.items
    .map((item) => t(changeItemLabelKey(item)))
    .join(t('portal.profile.listSeparator'))
}

function submittedAt(request) {
  return formatDate(request.submittedAt, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(() => goTo(1))
</script>

<template>
  <div class="history-requests" data-testid="profile-change-requests">
    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="goTo(1)" />
    <p v-else-if="status !== 'success'" class="history-drawer__empty" role="status">
      {{ t('portal.profile.history.requestsEmpty') }}
    </p>
    <template v-else>
      <article v-for="request in changes" :key="request.id" class="history-entry">
        <header class="history-entry__head">
          <StatusBadge :status="request.status" />
          <time class="history-entry__date" :datetime="request.submittedAt">{{
            submittedAt(request)
          }}</time>
        </header>
        <p class="history-entry__items">{{ itemsOf(request) }}</p>
        <p v-if="request.reviewNote" class="history-entry__reason">
          {{ t('portal.profile.history.requestReason', { reason: request.reviewNote }) }}
        </p>
      </article>
      <Pagination
        v-if="pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="goTo"
      />
    </template>
  </div>
</template>
