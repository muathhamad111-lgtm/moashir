<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CycleBlockedRequests from './CycleBlockedRequests.vue'
import { usePermissions } from '@/composables/usePermissions'
import { CYCLE_CLOSABLE_STATUSES, CYCLE_STATUSES, AWARD_CODES } from '@/constants/advanced'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  cycle: { type: Object, required: true },
  busy: { type: String, default: '' },
  blockingCycle: { type: Object, default: null },
  blocked: { type: Object, default: null },
  canReopen: { type: Boolean, default: false },
})

const emit = defineEmits(['run', 'reopen'])
const { t, locale } = useI18n()
const { can } = usePermissions()

const status = computed(() => props.cycle.status)
const manage = computed(() => props.cycle.canManage)
const showsFiltering = computed(
  () => can(PERMISSIONS.AWARD_FILTERING_VIEW) && ![CYCLE_STATUSES.DRAFT].includes(status.value),
)
const blockedOpen = computed(
  () => status.value === CYCLE_STATUSES.DRAFT && Boolean(props.blockingCycle),
)
const blockedRequests = computed(() =>
  props.blocked?.code === AWARD_CODES.REQUESTS_PENDING ? props.blocked.requests : [],
)
const anotherOpen = computed(() => props.blocked?.code === AWARD_CODES.ANOTHER_OPEN)
</script>

<template>
  <Card class="cycle-actions" :title="t('cycles.actions.title')">
    <AssessmentNotice
      v-if="blockedOpen"
      variant="warning"
      :title="t('cycles.actions.blockedOpen.title')"
      :message="
        t('cycles.actions.blockedOpen.message', {
          name: localizedText(blockingCycle.name, locale),
          year: blockingCycle.year,
        })
      "
    />
    <AssessmentNotice
      v-if="anotherOpen"
      variant="danger"
      live
      :title="t('cycles.actions.anotherOpen.title')"
      :message="t('cycles.actions.anotherOpen.message')"
    />
    <CycleBlockedRequests v-if="blockedRequests.length" :requests="blockedRequests" />
    <div class="cycle-actions__buttons">
      <template v-if="manage">
        <BaseButton
          v-if="status === CYCLE_STATUSES.DRAFT"
          variant="primary"
          icon="fa-solid fa-door-open"
          :loading="busy === 'open'"
          :disabled="blockedOpen"
          @click="emit('run', 'open')"
        >
          {{ t('cycles.actions.open.label') }}
        </BaseButton>
        <BaseButton
          v-if="status === CYCLE_STATUSES.SUBMISSION_OPEN"
          variant="secondary"
          icon="fa-solid fa-door-closed"
          :loading="busy === 'closeSubmissions'"
          @click="emit('run', 'closeSubmissions')"
        >
          {{ t('cycles.actions.closeSubmissions.label') }}
        </BaseButton>
        <BaseButton
          v-if="CYCLE_CLOSABLE_STATUSES.includes(status)"
          variant="danger"
          icon="fa-solid fa-box-archive"
          :loading="busy === 'close'"
          @click="emit('run', 'close')"
        >
          {{ t('cycles.actions.close.label') }}
        </BaseButton>
      </template>
      <BaseButton
        v-if="canReopen && status === CYCLE_STATUSES.CLOSED"
        variant="secondary"
        icon="fa-solid fa-rotate-left"
        :loading="busy === 'reopen'"
        @click="emit('reopen')"
      >
        {{ t('cycles.actions.reopen.label') }}
      </BaseButton>
      <RouterLink
        v-if="showsFiltering"
        class="btn btn--ghost btn--md"
        :to="{ name: ROUTE_NAMES.AWARD_FILTERING_QUEUE, params: { id: cycle.id } }"
      >
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        {{ t('cycles.actions.filtering') }}
      </RouterLink>
    </div>
    <p v-if="!manage" class="cycle-actions__note">{{ t('cycles.actions.readOnly') }}</p>
  </Card>
</template>
