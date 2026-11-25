<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AUDIT_ACTOR_KINDS } from '@/constants/auditLog'
import { actorRolesText } from '@/utils/audit/actorRoles'

const props = defineProps({
  actor: { type: Object, required: true },
})

const { t, te } = useI18n()

const name = computed(() => {
  if (props.actor.kind === AUDIT_ACTOR_KINDS.USER && props.actor.name) return props.actor.name
  return t(`auditLog.actors.${props.actor.kind}`)
})
const roles = computed(() => actorRolesText(props.actor.roles, { t, te }))
</script>

<template>
  <span class="audit-log__actor">
    <span>{{ name }}</span>
    <span v-if="actor.id" class="audit-log__muted"
      ><bdi dir="ltr">#{{ actor.id }}</bdi></span
    >
    <span v-if="roles" class="audit-log__muted">{{ roles }}</span>
  </span>
</template>
