<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileChangeReviewDrawer from './ProfileChangeReviewDrawer.vue'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { PROFILE_CHANGE_STATUSES } from '@/constants/entityEnums'
import { logger } from '@/utils/logger'

const props = defineProps({
  entityId: { type: Number, required: true },
})

const emit = defineEmits(['decided'])
const { t } = useI18n()
const { dateTime } = useEntityDisplay()
const pending = ref(null)
const reviewOpen = ref(false)
const count = computed(() => pending.value?.items?.length ?? 0)

async function load() {
  const { data } = await profileChangeRequestsService.list({
    'filter[status]': PROFILE_CHANGE_STATUSES.PENDING,
    'filter[entityId]': props.entityId,
    perPage: 1,
  })
  pending.value = data?.[0] ?? null
}

async function onDecided() {
  await load()
  emit('decided')
}

onMounted(() =>
  load().catch((error) => {
    pending.value = null
    logger.error(error, { action: 'entities.profileChanges.recordCard.load' })
  }),
)
</script>

<template>
  <Card
    v-if="pending"
    class="entity-pending-change"
    :title="t('entities.profileChanges.recordCard.title')"
    data-testid="entity-pending-change"
  >
    <p class="entity-pending-change__text">
      {{
        t(
          'entities.profileChanges.recordCard.text',
          {
            count,
            date: dateTime(pending.submittedAt),
          },
          count,
        )
      }}
    </p>
    <BaseButton variant="primary" icon="pen-line" @click="reviewOpen = true">
      {{ t('entities.profileChanges.recordCard.open') }}
    </BaseButton>
    <ProfileChangeReviewDrawer v-model="reviewOpen" :request-id="pending.id" @decided="onDecided" />
  </Card>
</template>
