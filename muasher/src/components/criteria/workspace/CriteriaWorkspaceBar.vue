<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useCriteria } from '@/composables/useCriteria'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { MENU_TRIGGERS } from '@/constants/dropdown'
import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'

const props = defineProps({
  version: { type: Object, required: true },
  draft: { type: Object, default: null },
  versions: { type: Array, required: true },
  problemCount: { type: Number, default: 0 },
  canManage: { type: Boolean, default: false },
  canPublish: { type: Boolean, default: false },
})

const emit = defineEmits(['start', 'review', 'discard', 'clone', 'correct-name', 'show-version'])
const { t } = useI18n()
const router = useRouter()
const { localized } = useCriteria()

const isDraft = computed(() => props.version.status === STATUS.DRAFT)
const isArchived = computed(() => props.version.status === STATUS.ARCHIVED)
const label = computed(() => localized(props.version.label))
const title = computed(() =>
  t(`criteria.workspace.bar.title.${props.version.status}`, {
    number: props.version.versionNumber,
  }),
)
const meta = computed(() => {
  if (!isDraft.value)
    return { tone: '', text: t(`criteria.workspace.bar.meta.${props.version.status}`) }
  if (props.problemCount) {
    return {
      tone: 'danger',
      text: t(
        'criteria.workspace.bar.meta.blocked',
        { count: props.problemCount },
        props.problemCount,
      ),
    }
  }
  return { tone: 'success', text: t('criteria.workspace.bar.meta.ready') }
})
const canStart = computed(() => props.canManage && !isDraft.value && !props.draft)

const menuActions = computed(() => [
  { key: 'history', label: t('criteria.history.open'), icon: 'docs' },
  ...(canStart.value
    ? [{ key: 'clone', label: t('criteria.workspace.bar.clone'), icon: 'copy' }]
    : []),
  ...(props.canManage && !isDraft.value
    ? [{ key: 'correct-name', label: t('criteria.version.correctLabel'), icon: 'pen-line' }]
    : []),
  ...props.versions
    .filter((version) => version.id !== props.version.id)
    .map((version) => ({
      key: `version-${version.id}`,
      label: t(`criteria.workspace.bar.show.${version.status}`, { number: version.versionNumber }),
    })),
])

function onMenu(key) {
  if (key === 'history') return router.push({ name: ROUTE_NAMES.ADMIN_CRITERIA_HISTORY })
  if (key.startsWith('version-')) return emit('show-version', Number(key.slice('version-'.length)))
  return emit(key)
}
</script>

<template>
  <section class="criteria-bar" :aria-label="t('criteria.workspace.bar.label')">
    <div class="criteria-bar__state">
      <div class="criteria-bar__title-row">
        <h2 class="criteria-bar__title">{{ title }}</h2>
        <Badge v-if="label" variant="muted">{{ label }}</Badge>
      </div>
      <p class="criteria-bar__meta" :class="meta.tone && `criteria-bar__meta--${meta.tone}`">
        <AppIcon v-if="meta.tone === 'danger'" name="warning-triangle" />
        <AppIcon v-else-if="meta.tone === 'success'" name="check-circle" />
        {{ meta.text }}
      </p>
    </div>
    <div class="criteria-bar__actions">
      <BaseButton v-if="canStart" variant="primary" icon="pen-line" @click="emit('start')">
        {{ t('criteria.workspace.bar.start') }}
      </BaseButton>
      <BaseButton
        v-else-if="!isDraft && draft"
        variant="primary"
        @click="emit('show-version', draft.id)"
      >
        {{ t('criteria.workspace.bar.continue') }}
      </BaseButton>
      <template v-if="isDraft">
        <BaseButton v-if="canPublish" variant="primary" icon="upload" @click="emit('review')">
          {{ t('criteria.workspace.bar.review') }}
        </BaseButton>
        <BaseButton v-if="canManage" variant="secondary" icon="trash" @click="emit('discard')">
          {{ t('criteria.workspace.bar.discard') }}
        </BaseButton>
      </template>
      <BaseButton v-if="isArchived" variant="secondary" @click="emit('show-version', null)">
        {{ t('criteria.workspace.bar.backToCurrent') }}
      </BaseButton>
      <AppMenu
        :trigger="MENU_TRIGGERS.button"
        :actions="menuActions"
        :label="t('criteria.workspace.bar.versions')"
        @select="onMenu"
      >
        <template #trigger>{{ t('criteria.workspace.bar.versions') }}</template>
      </AppMenu>
    </div>
  </section>
</template>
