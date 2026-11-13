<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { useConfirm } from '@/composables/useConfirm'
import { CYCLE_STATUSES } from '@/constants/advanced'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  cycleStatus: { type: String, default: '' },
  national: { type: Object, required: true },
  canAnnounce: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
})

const emit = defineEmits(['announce'])
const { t } = useI18n()
const { confirm } = useConfirm()
const ready = computed(() => props.cycleStatus === CYCLE_STATUSES.APPROVED)

async function announce() {
  const accepted = await confirm({
    title: t('award.announce.confirmTitle'),
    message: t('award.announce.confirmMessage'),
    confirmLabel: t('award.announce.confirm'),
    variant: 'warning',
  })
  if (accepted) emit('announce')
}
</script>

<template>
  <Card class="announcement-card" :title="t('award.announce.title')">
    <p v-if="national.announcedAt" class="announcement-card__done">
      <AppIcon name="megaphone" /> {{ t('award.announce.done') }}
      <DateStamp :value="national.announcedAt" />
    </p>
    <template v-else>
      <p class="announcement-card__text">
        {{ ready ? t('award.announce.ready') : t('award.announce.notReady') }}
      </p>
      <p v-if="national.approvedAt" class="committee-member__note">
        {{ t('award.announce.approvedAt') }} <DateStamp :value="national.approvedAt" />
      </p>
      <BaseButton
        v-if="canAnnounce"
        variant="primary"
        icon="megaphone"
        :loading="busy"
        :disabled="!ready || locked"
        @click="announce"
        >{{ t('award.announce.action') }}</BaseButton
      >
      <p v-else class="cycle-actions__note">{{ t('award.announce.secretaryOnly') }}</p>
    </template>
  </Card>
</template>
