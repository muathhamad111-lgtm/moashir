<script setup>
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'

defineProps({
  requests: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <table class="open-requests">
    <caption class="open-requests__caption">
      {{
        t('entities.openRequests.caption')
      }}
    </caption>
    <thead>
      <tr>
        <th scope="col">{{ t('entities.openRequests.number') }}</th>
        <th scope="col">{{ t('entities.openRequests.path') }}</th>
        <th scope="col">{{ t('entities.openRequests.status') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="request in requests" :key="request.number" class="open-requests__row">
        <th scope="row" class="open-requests__number u-ltr">{{ request.number }}</th>
        <td>{{ t(`entities.enums.requestPath.${request.path}`) }}</td>
        <td>
          <span class="open-requests__status">
            <StatusBadge :status="request.status" />
            <FrozenBadge v-if="request.isFrozen" />
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</template>
