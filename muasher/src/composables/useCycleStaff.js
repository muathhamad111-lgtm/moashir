import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { CANDIDATE_ROLES, MEMBER_ROLES } from '@/constants/advanced'

const ROLE_FIELDS = {
  [MEMBER_ROLES.FILTERING_OFFICER]: 'filteringOfficerIds',
  [MEMBER_ROLES.AWARD_SECRETARY]: 'awardSecretaryIds',
}

function idsOf(members, role) {
  return members.filter((member) => member.role === role).map((member) => member.id)
}

export function useCycleStaff({ editor, reasonExtra }) {
  const { t } = useI18n()
  const toast = useToast()
  const candidates = ref({})
  const loading = ref(false)
  const saving = ref('')
  const selection = ref({ [MEMBER_ROLES.FILTERING_OFFICER]: [], [MEMBER_ROLES.AWARD_SECRETARY]: [], [CANDIDATE_ROLES.INITIAL_ASSESSOR]: [] })

  const cycle = computed(() => editor.cycle.value)

  function resetSelection() {
    const value = cycle.value
    if (!value) return
    selection.value = {
      [MEMBER_ROLES.FILTERING_OFFICER]: idsOf(value.members, MEMBER_ROLES.FILTERING_OFFICER),
      [MEMBER_ROLES.AWARD_SECRETARY]: idsOf(value.members, MEMBER_ROLES.AWARD_SECRETARY),
      [CANDIDATE_ROLES.INITIAL_ASSESSOR]: value.initialAssessors.filter((link) => link.isActive).map((link) => link.id),
    }
  }

  function optionsFor(role) {
    const known = new Map((candidates.value[role] ?? []).map((person) => [person.id, person.name]))
    const current = role === CANDIDATE_ROLES.INITIAL_ASSESSOR ? (cycle.value?.initialAssessors ?? []) : (cycle.value?.members ?? []).filter((member) => member.role === role)
    current.forEach((person) => known.set(person.id, known.get(person.id) ?? person.name))
    return [...known.entries()].map(([value, label]) => ({ value, label }))
  }

  async function loadCandidates() {
    loading.value = true
    try {
      const roles = Object.values(CANDIDATE_ROLES)
      const lists = await Promise.all(roles.map((role) => awardCycleService.listCandidates({ role })))
      candidates.value = Object.fromEntries(roles.map((role, index) => [role, lists[index].data]))
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      loading.value = false
    }
  }

  async function save(kind, send) {
    if (saving.value) return
    saving.value = kind
    try {
      const extra = await reasonExtra()
      if (extra === null) return
      const { data } = await send(extra)
      editor.apply(data)
      resetSelection()
      toast.success(t(`cycles.staff.${kind}.saved`))
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      saving.value = ''
    }
  }

  function saveMembers() {
    const payload = Object.fromEntries(Object.entries(ROLE_FIELDS).map(([role, field]) => [field, selection.value[role]]))
    return save('members', (extra) => awardCycleService.syncMembers(cycle.value.id, { ...payload, ...extra }))
  }

  function saveInitialAssessors() {
    const assessorIds = selection.value[CANDIDATE_ROLES.INITIAL_ASSESSOR]
    return save('assessors', (extra) => awardCycleService.syncInitialAssessors(cycle.value.id, { assessorIds, ...extra }))
  }

  function select(role, ids) {
    selection.value = { ...selection.value, [role]: ids }
  }

  watch(cycle, resetSelection, { immediate: true })

  return { selection, loading, saving, optionsFor, loadCandidates, select, saveMembers, saveInitialAssessors }
}
