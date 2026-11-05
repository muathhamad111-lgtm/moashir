<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ACCREDITED_STATUSES, FORCE_BLOCKED_STATUSES } from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

const props = defineProps({
  detail: { type: Object, required: true },
  canAssign: { type: Boolean, default: false },
  canReassign: { type: Boolean, default: false },
  bundleRequesting: { type: Boolean, default: false },
  canReturn: { type: Boolean, default: false },
  canWithdraw: { type: Boolean, default: false },
})

const emit = defineEmits(['assign', 'reassign', 'bundle', 'reopen', 'force', 'return', 'withdraw'])
const { t } = useI18n()
const router = useRouter()
const { can, canAny } = usePermissions()
const auth = useAuthStore()

const mayAssign = computed(() => can(PERMISSIONS.REQUESTS_ASSIGN))
const isAccredited = computed(() => props.detail.path === ASSESSMENT_PATHS.ACCREDITED)
const mayReview = computed(
  () =>
    canAny([PERMISSIONS.REQUESTS_REVIEW_READ, PERMISSIONS.ASSESSMENT_SCORE_MANAGE]) &&
    props.detail.assignmentHistory.length > 0,
)
const mayBundle = computed(() => can(PERMISSIONS.EVIDENCE_VIEW))
const mayReopen = computed(
  () =>
    auth.isSuperAdmin &&
    isAccredited.value &&
    props.detail.status === ACCREDITED_STATUSES.COMPLETED,
)
const mayForce = computed(
  () =>
    auth.isSuperAdmin &&
    isAccredited.value &&
    !FORCE_BLOCKED_STATUSES.includes(props.detail.status),
)

function openReview() {
  router.push({ name: ROUTE_NAMES.REQUESTS_REVIEW, params: { id: props.detail.id } })
}
</script>

<template>
  <Card class="request-actions">
    <template #header>
      <h2 class="card__title-text">{{ t('requests.detail.actions') }}</h2>
    </template>
    <div class="request-actions__buttons">
      <BaseButton
        v-if="mayAssign && canAssign"
        variant="primary"
        icon="fa-solid fa-user-check"
        @click="emit('assign')"
        >{{ t('requests.actions.assign') }}</BaseButton
      >
      <BaseButton
        v-if="mayAssign && canReassign"
        variant="secondary"
        icon="fa-solid fa-user-pen"
        @click="emit('reassign')"
        >{{ t('requests.actions.reassign') }}</BaseButton
      >
      <BaseButton
        v-if="canReturn"
        variant="secondary"
        icon="fa-solid fa-reply"
        @click="emit('return')"
        >{{ t('requests.actions.return') }}</BaseButton
      >
      <BaseButton
        v-if="mayReview"
        variant="secondary"
        icon="fa-solid fa-clipboard-check"
        @click="openReview"
        >{{ t('requests.actions.openReview') }}</BaseButton
      >
      <BaseButton
        v-if="mayBundle"
        variant="secondary"
        icon="fa-solid fa-file-zipper"
        :loading="bundleRequesting"
        @click="emit('bundle')"
        >{{ t('requests.actions.bundle') }}</BaseButton
      >
      <BaseButton
        v-if="canWithdraw"
        variant="danger"
        icon="fa-solid fa-ban"
        @click="emit('withdraw')"
        >{{ t('requests.actions.withdraw') }}</BaseButton
      >
      <BaseButton
        v-if="mayReopen"
        variant="danger"
        icon="fa-solid fa-lock-open"
        @click="emit('reopen')"
        >{{ t('requests.overrides.reopen') }}</BaseButton
      >
      <BaseButton
        v-if="mayForce"
        variant="danger"
        icon="fa-solid fa-shuffle"
        @click="emit('force')"
        >{{ t('requests.overrides.force') }}</BaseButton
      >
    </div>
    <p class="request-actions__note">{{ t('requests.detail.bundleNote') }}</p>
  </Card>
</template>
