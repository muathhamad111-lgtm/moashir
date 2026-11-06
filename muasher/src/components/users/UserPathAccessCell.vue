<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PathBadge from '@/components/ui/PathBadge.vue'
import { QUALIFICATION_PATHS } from '@/constants/paths'
import { isQualifiableUser, isScopableUser } from '@/utils/users/pathAccessRoles'

const props = defineProps({
  user: { type: Object, required: true },
})

const { t } = useI18n()

const qualifiedPaths = computed(() =>
  props.user.assessorQualifications.map((value) => QUALIFICATION_PATHS[value]).filter(Boolean),
)
</script>

<template>
  <div class="path-access-cells">
    <div v-if="isQualifiableUser(user)" class="path-access-cell">
      <span class="path-access-cell__muted">{{ t('users.pathAccess.qualifiedFor') }}</span>
      <PathBadge v-for="path in qualifiedPaths" :key="path" :path="path" compact />
      <span v-if="!qualifiedPaths.length" class="path-access-cell__muted">{{
        t('users.pathAccess.noQualification')
      }}</span>
    </div>
    <div v-if="isScopableUser(user)" class="path-access-cell">
      <span class="path-access-cell__muted">{{ t('users.pathAccess.follows') }}</span>
      <span v-if="user.requestScope.allPaths" class="path-access-cell__muted">{{
        t('users.pathAccess.allPaths')
      }}</span>
      <template v-else>
        <PathBadge v-for="path in user.requestScope.paths" :key="path" :path="path" compact />
      </template>
    </div>
  </div>
</template>
