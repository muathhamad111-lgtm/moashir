<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useBulkQualification } from '@/composables/useBulkQualification'
import { useConfirm } from '@/composables/useConfirm'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { errorMessageOf } from '@/utils/award/loadState'

const props = defineProps({
  cycleId: { type: Number, required: true },
  windowOpen: { type: Boolean, default: false },
})
const emit = defineEmits(['done'])

const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const bulk = useBulkQualification(computed(() => props.cycleId))
const count = computed(() => bulk.preview.value?.count ?? 0)
const shown = computed(() => props.windowOpen && count.value > 0)

async function qualifyAll() {
  const preview = bulk.preview.value
  const accepted = await confirm({
    title: t('filtering.bulk.confirmTitle', { count: preview.count }),
    message: t('filtering.bulk.confirmMessage', {
      count: preview.count,
      threshold: preview.thresholdPercent,
    }),
    confirmLabel: t('filtering.bulk.confirm'),
    variant: 'confirm',
  })
  if (!accepted) return
  const extra = await reasonExtra()
  if (extra === null) return
  try {
    const outcome = await bulk.run(extra)
    toast.success(t('filtering.bulk.done', { count: outcome.qualified }))
    emit('done')
  } catch (error) {
    toast.error(errorMessageOf(error))
  }
}
</script>

<template>
  <div v-if="shown" class="bulk-qualify">
    <BaseButton
      variant="primary"
      icon="check-circle"
      data-testid="bulk-qualify"
      :loading="bulk.busy.value"
      @click="qualifyAll"
      >{{ t('filtering.bulk.action', { count }) }}</BaseButton
    >
    <p v-if="bulk.preview.value.ownAssessments" class="bulk-qualify__note" role="note">
      {{ t('filtering.bulk.ownNote', { count: bulk.preview.value.ownAssessments }) }}
    </p>
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('cycles.reason.title')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="filtering.bulkReason"
    />
  </div>
</template>
