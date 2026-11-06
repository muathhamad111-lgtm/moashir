<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuditActorCell from './AuditActorCell.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'

const JSON_INDENT = 2

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  entry: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()

function pretty(values) {
  return values && Object.keys(values).length ? JSON.stringify(values, null, JSON_INDENT) : ''
}

const oldValues = computed(() => pretty(props.entry?.oldValues))
const newValues = computed(() => pretty(props.entry?.newValues))
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('auditLog.dialog.title')"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <dl v-if="entry" class="audit-entry">
      <div>
        <dt>{{ t('auditLog.columns.createdAt') }}</dt>
        <dd>
          <bdi>{{ formatRiyadhDateTime(entry.createdAt, locale) }}</bdi>
        </dd>
      </div>
      <div>
        <dt>{{ t('auditLog.columns.event') }}</dt>
        <dd>
          <code dir="ltr">{{ entry.event }}</code>
        </dd>
      </div>
      <div>
        <dt>{{ t('auditLog.columns.actor') }}</dt>
        <dd><AuditActorCell :actor="entry.actor" /></dd>
      </div>
      <div v-if="entry.subject">
        <dt>{{ t('auditLog.columns.subject') }}</dt>
        <dd>
          <bdi dir="ltr">{{ entry.subject.type }} #{{ entry.subject.id }}</bdi>
        </dd>
      </div>
      <div v-if="entry.reason">
        <dt>{{ t('auditLog.columns.reason') }}</dt>
        <dd>{{ entry.reason }}</dd>
      </div>
      <div v-if="entry.tags.length">
        <dt>{{ t('auditLog.dialog.tags') }}</dt>
        <dd>
          <bdi dir="ltr">{{ entry.tags.join(', ') }}</bdi>
        </dd>
      </div>
      <div>
        <dt>{{ t('auditLog.columns.ipAddress') }}</dt>
        <dd>
          <bdi dir="ltr">{{ entry.ipAddress }}</bdi>
        </dd>
      </div>
      <div v-if="entry.url">
        <dt>{{ t('auditLog.dialog.url') }}</dt>
        <dd>
          <bdi class="audit-entry__wrap" dir="ltr">{{ entry.url }}</bdi>
        </dd>
      </div>
      <div v-if="entry.userAgent">
        <dt>{{ t('auditLog.dialog.userAgent') }}</dt>
        <dd>
          <bdi class="audit-entry__wrap" dir="ltr">{{ entry.userAgent }}</bdi>
        </dd>
      </div>
      <div v-if="entry.requestId">
        <dt>{{ t('auditLog.dialog.requestId') }}</dt>
        <dd>
          <bdi dir="ltr">{{ entry.requestId }}</bdi>
        </dd>
      </div>
    </dl>
    <div v-if="entry" class="audit-entry__values">
      <section>
        <h3 class="audit-entry__heading">{{ t('auditLog.dialog.before') }}</h3>
        <pre v-if="oldValues" class="audit-entry__json" dir="ltr">{{ oldValues }}</pre>
        <p v-else class="audit-log__muted">{{ t('auditLog.dialog.noValues') }}</p>
      </section>
      <section>
        <h3 class="audit-entry__heading">{{ t('auditLog.dialog.after') }}</h3>
        <pre v-if="newValues" class="audit-entry__json" dir="ltr">{{ newValues }}</pre>
        <p v-else class="audit-log__muted">{{ t('auditLog.dialog.noValues') }}</p>
      </section>
    </div>
    <template #footer>
      <BaseButton variant="secondary" @click="emit('update:modelValue', false)">{{
        t('actions.close')
      }}</BaseButton>
    </template>
  </BaseModal>
</template>
