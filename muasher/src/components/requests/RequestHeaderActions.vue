<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import RequestAssignButton from './RequestAssignButton.vue'
import { REQUEST_MENU_ACTIONS, useRequestActions } from '@/composables/useRequestActions'
import { overrideReviewRoute, reviewRoute } from '@/utils/reviewAction'

// The request page's actions, always at the end of its header: the state's main action first.
const props = defineProps({
  detail: { type: Object, required: true },
  assignmentMode: { type: String, default: null },
  bundleRequesting: { type: Boolean, default: false },
  canReturn: { type: Boolean, default: false },
  canWithdraw: { type: Boolean, default: false },
})

const emit = defineEmits(['assign', 'bundle', 'reopen', 'force', 'return', 'withdraw'])
const { t } = useI18n()
const router = useRouter()
const { mayReview, reviewLabel, assignsFirst, mayBundle, menuActions } = useRequestActions(
  toRef(props, 'detail'),
  {
    assignmentMode: toRef(props, 'assignmentMode'),
    canReturn: toRef(props, 'canReturn'),
    canWithdraw: toRef(props, 'canWithdraw'),
  },
)

function onMenu(key) {
  if (key === REQUEST_MENU_ACTIONS.OVERRIDE) router.push(overrideReviewRoute(props.detail.id))
  else emit(key)
}
</script>

<template>
  <div class="request-actions" role="group" :aria-label="t('requests.detail.actions')">
    <RequestAssignButton
      v-if="assignsFirst"
      :mode="assignmentMode"
      size="md"
      @assign="emit('assign', $event)"
    />
    <BaseButton
      v-else-if="mayReview"
      variant="primary"
      icon="clipboard-check"
      data-testid="request-open-review"
      :to="reviewRoute(detail.id)"
      >{{ reviewLabel }}</BaseButton
    >
    <RequestAssignButton
      v-if="assignmentMode && !assignsFirst"
      :mode="assignmentMode"
      size="md"
      @assign="emit('assign', $event)"
    />
    <BaseButton
      v-if="mayBundle"
      variant="secondary"
      icon="docs"
      :loading="bundleRequesting"
      :title="t('requests.detail.bundleNote')"
      data-testid="request-bundle"
      @click="emit('bundle')"
      >{{ t('requests.actions.bundle') }}</BaseButton
    >
    <AppMenu
      v-if="menuActions.length"
      :actions="menuActions"
      :label="t('requests.detail.moreActions', { number: detail.number })"
      @select="onMenu"
    />
  </div>
</template>
