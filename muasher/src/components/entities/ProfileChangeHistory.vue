<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import { useProfileChanges } from '@/composables/useProfileChanges'
import { useProfileChangeFormat } from '@/composables/useProfileChangeFormat'

const props = defineProps({
  loader: { type: Function, required: true },
  showStaffIdentity: { type: Boolean, default: false },
})

const { t } = useI18n()
const { changes, status, errorMessage, pagination, load, goTo } = useProfileChanges((params) =>
  props.loader(params),
)
const { valueLabel, fieldLabel, actorLabel, changedAt } = useProfileChangeFormat()

onMounted(load)
</script>

<template>
  <Card :title="t('entities.history.title')" class="profile-history">
    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <p v-else-if="status === 'forbidden'" class="profile-history__empty" role="status">
      {{ t('entities.history.forbidden') }}
    </p>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <p v-else-if="status === 'empty'" class="profile-history__empty" role="status">
      {{ t('entities.history.empty') }}
    </p>
    <template v-else>
      <div class="profile-history__scroll">
        <table class="profile-history__table">
          <caption class="profile-history__caption">
            {{
              t('entities.history.caption')
            }}
          </caption>
          <thead>
            <tr>
              <th scope="col">{{ t('entities.history.columns.field') }}</th>
              <th scope="col">{{ t('entities.history.columns.before') }}</th>
              <th scope="col">{{ t('entities.history.columns.after') }}</th>
              <th scope="col">{{ t('entities.history.columns.changedBy') }}</th>
              <th scope="col">{{ t('entities.history.columns.changedAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="change in changes" :key="change.id">
              <th scope="row">{{ fieldLabel(change) }}</th>
              <td dir="auto">{{ valueLabel(change.field, change.oldValue) }}</td>
              <td dir="auto">{{ valueLabel(change.field, change.newValue) }}</td>
              <td>{{ actorLabel(change, { showStaffIdentity }) }}</td>
              <td class="profile-history__date">{{ changedAt(change) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination
        v-if="pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="goTo"
      />
    </template>
  </Card>
</template>
