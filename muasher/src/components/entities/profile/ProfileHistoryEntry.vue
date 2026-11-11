<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProfileChangeFormat } from '@/composables/useProfileChangeFormat'

const ENTITY_ACTOR = 'entity'
const STAFF_ACTOR = 'staff'

const props = defineProps({
  change: { type: Object, required: true },
  entityUserName: { type: String, default: '' },
})

const { t } = useI18n()
const { valueLabel, fieldLabel, changedAt } = useProfileChangeFormat()

const actor = computed(() => {
  if (props.change.changedBy === ENTITY_ACTOR)
    return props.entityUserName || t('portal.profile.history.actors.entity')
  if (props.change.changedBy === STAFF_ACTOR) return t('portal.profile.history.actors.staff')
  return t('portal.profile.history.actors.system')
})
</script>

<template>
  <article class="history-entry" data-testid="profile-history-entry">
    <header class="history-entry__head">
      <h3 class="history-entry__field">{{ fieldLabel(change) }}</h3>
      <time class="history-entry__date" :datetime="change.changedAt">{{ changedAt(change) }}</time>
    </header>
    <dl class="history-entry__rows">
      <div class="history-entry__row">
        <dt>{{ t('portal.profile.history.before') }}</dt>
        <dd dir="auto">{{ valueLabel(change.field, change.oldValue) }}</dd>
      </div>
      <div class="history-entry__row history-entry__row--after">
        <dt>{{ t('portal.profile.history.after') }}</dt>
        <dd dir="auto">{{ valueLabel(change.field, change.newValue) }}</dd>
      </div>
      <div class="history-entry__row">
        <dt>{{ t('portal.profile.history.by') }}</dt>
        <dd>{{ actor }}</dd>
      </div>
    </dl>
  </article>
</template>
