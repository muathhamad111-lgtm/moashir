<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import GapCard from '@/components/bag/GapCard.vue'
import GapGuidanceEditor from '@/components/bag/GapGuidanceEditor.vue'
import BagContextTracks from '@/components/bag/BagContextTracks.vue'
import { useBagConsultant } from '@/composables/useBag'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()

const {
  status,
  errorMessage,
  gaps,
  stages,
  tracks,
  activeStage,
  isSelected,
  selectedCount,
  opening,
  attaching,
  savingGuidance,
  localized,
  load,
  toggle,
  openSelected,
  attachFile,
  saveGuidance,
} = useBagConsultant()

useHead({ title: () => t('bag.manage.title') })

onMounted(load)

const activeStageTitle = computed(() =>
  activeStage.value ? localized(activeStage.value.title) : t('bag.manage.noActiveStage'),
)

async function onOpenSelected() {
  if (!selectedCount.value) return
  const ok = await confirm({
    title: t('bag.manage.openSelected.confirmTitle'),
    message: t('bag.manage.openSelected.confirmMessage', { stage: activeStageTitle.value }),
    confirmLabel: t('bag.manage.openSelected.confirmAction'),
    variant: 'confirm',
  })
  if (!ok) return
  try {
    await openSelected()
    toast.success(t('bag.manage.openSelected.done'))
  } catch {
    toast.error(t('bag.manage.openSelected.failed'))
  }
}

async function onAttach(gap, files) {
  const file = files?.[0]
  if (!file) return
  try {
    await attachFile(gap, file)
    toast.success(t('bag.manage.attach.done'))
  } catch {
    toast.error(t('bag.manage.attach.failed'))
  }
}

async function onSaveGuidance(gap, payload) {
  try {
    await saveGuidance(gap, payload)
    toast.success(t('bag.manage.guidance.saved'))
  } catch {
    toast.error(t('bag.manage.guidance.failed'))
  }
}
</script>

<template>
  <section class="bag-manage">
    <div v-if="status === 'loading'" class="bag-manage__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="folder"
      :title="t('bag.manage.empty.title')"
      :message="t('bag.manage.empty.message')"
    />

    <template v-else>
      <header class="bag-manage__header">
        <div>
          <p class="bag-manage__eyebrow">{{ t('bag.manage.eyebrow') }}</p>
          <h1 class="bag-manage__title">{{ t('bag.manage.title') }}</h1>
        </div>
      </header>

      <p class="bag-manage__intro">{{ t('bag.manage.intro') }}</p>

      <BagContextTracks v-if="tracks" :tracks="tracks" />

      <section class="bag-manage__stages" aria-labelledby="bag-stages-title">
        <h2 id="bag-stages-title" class="bag-manage__section-title">
          {{ t('bag.manage.stages.title') }}
        </h2>
        <ul class="bag-manage__stage-list">
          <li v-for="s in stages" :key="s.id" class="bag-manage__stage">
            <Card>
              <div class="bag-manage__stage-head">
                <div>
                  <p class="bag-manage__stage-order">
                    {{ t('bag.manage.stages.order', { order: s.order }) }}
                  </p>
                  <h3 class="bag-manage__stage-name">{{ localized(s.title) }}</h3>
                </div>
                <StatusBadge :status="s.status" />
              </div>
              <p v-if="s.description" class="bag-manage__stage-desc">
                {{ localized(s.description) }}
              </p>
            </Card>
          </li>
        </ul>
      </section>

      <div class="bag-manage__toolbar">
        <p class="bag-manage__active-stage">
          {{ t('bag.manage.activeStage', { stage: activeStageTitle }) }}
        </p>
        <div class="bag-manage__toolbar-actions">
          <span class="bag-manage__selected-count" aria-live="polite">
            {{ t('bag.manage.selectedCount', { count: selectedCount }) }}
          </span>
          <BaseButton
            variant="primary"
            icon="folder"
            :disabled="selectedCount === 0"
            :loading="opening"
            @click="onOpenSelected"
          >
            {{ t('bag.manage.openSelected.action') }}
          </BaseButton>
        </div>
      </div>

      <h2 class="bag-manage__section-title">{{ t('bag.manage.allGaps.title') }}</h2>
      <ul class="bag-manage__gaps">
        <li v-for="gap in gaps" :key="gap.id" class="bag-manage__gap">
          <div class="bag-manage__gap-select">
            <label class="bag-manage__checkbox">
              <input
                type="checkbox"
                class="bag-manage__checkbox-input"
                :checked="isSelected(gap.id)"
                :disabled="gap.open"
                @change="toggle(gap.id)"
              />
              <span class="bag-manage__checkbox-label">
                {{ gap.open ? t('bag.manage.alreadyOpen') : t('bag.manage.selectGap') }}
              </span>
            </label>
          </div>

          <GapCard :gap="gap" :localized="localized" :downloadable="false" />

          <GapGuidanceEditor
            :gap="gap"
            :saving="Boolean(savingGuidance[gap.id])"
            :localized="localized"
            @save="(payload) => onSaveGuidance(gap, payload)"
          />

          <div v-if="gap.open" class="bag-manage__attach">
            <BaseFileUpload
              :label="t('bag.manage.attach.label')"
              :name="`bag-gap-file-${gap.id}`"
              :hint="t('bag.manage.attach.hint')"
              :disabled="Boolean(attaching[gap.id])"
              @select="(files) => onAttach(gap, files)"
            />
          </div>
        </li>
      </ul>
    </template>
  </section>
</template>
