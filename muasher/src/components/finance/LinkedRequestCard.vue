<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  linkedRequest: { type: Object, default: null },
  paymentStatus: { type: String, required: true },
  entityId: { type: Number, default: null },
})

const { t } = useI18n()
const { can } = usePermissions()

const emptyKey = computed(() =>
  props.paymentStatus === PAYMENT_STATUSES.PAID
    ? 'finance.linkedRequest.notOpenedPaid'
    : 'finance.linkedRequest.notOpened',
)
const opensEntity = computed(() => props.entityId !== null && can(PERMISSIONS.ENTITIES_VIEW))
</script>

<template>
  <Card :title="t('finance.linkedRequest.title')" data-testid="linked-request-card">
    <dl v-if="linkedRequest" class="transfer-facts">
      <div class="transfer-facts__row">
        <dt>{{ t('finance.linkedRequest.number') }}</dt>
        <dd class="linked-request__number">
          <bdi dir="ltr">{{ linkedRequest.number }}</bdi>
          <PathBadge
            v-if="linkedRequest.path"
            :path="linkedRequest.path"
            :label="linkedRequest.pathLabel"
            compact
          />
        </dd>
      </div>
      <div class="transfer-facts__row">
        <dt>{{ t('finance.linkedRequest.status') }}</dt>
        <dd><StatusBadge :status="linkedRequest.status" /></dd>
      </div>
    </dl>
    <p v-else class="linked-request__empty">{{ t(emptyKey) }}</p>
    <ProgressMeter
      v-if="linkedRequest?.progress"
      class="linked-request__progress"
      :label="t('finance.linkedRequest.progress')"
      :current="linkedRequest.progress.complete"
      :total="linkedRequest.progress.total"
    />
    <p v-if="linkedRequest && !linkedRequest.id" class="linked-request__restricted" role="note">
      <AppIcon name="lock" />
      {{ t('finance.linkedRequest.restricted') }}
    </p>
    <div v-if="linkedRequest?.id || opensEntity" class="linked-request__links">
      <RouterLink
        v-if="linkedRequest?.id"
        class="linked-request__link"
        :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: linkedRequest.id } }"
      >
        {{ t('finance.linkedRequest.open') }}
        <AppIcon name="arrow-right" class="linked-request__arrow" />
      </RouterLink>
      <RouterLink
        v-if="opensEntity"
        class="linked-request__link"
        :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: entityId } }"
      >
        {{ t('finance.linkedRequest.openEntity') }}
        <AppIcon name="arrow-right" class="linked-request__arrow" />
      </RouterLink>
    </div>
  </Card>
</template>
