import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import {
  BAG_SOURCE_ASSESSMENT_ID,
  bagStagesFixture,
  seedGaps,
  seedSubscription,
  seedEligibility,
  seedReference,
  seedAssessmentContext,
  seedBagHistory,
  buildReassessment,
} from '../fixtures/bag'
import { seedBagProduct } from '../fixtures/bagProduct'
import { failsRandomly } from '../contract/faults'

const store = {
  subscription: seedSubscription(),
  gaps: seedGaps(),
  stages: bagStagesFixture.map((s) => ({ ...s })),
  product: seedBagProduct(),
}

export function resetBagStore() {
  store.subscription = seedSubscription()
  store.gaps = seedGaps()
  store.stages = bagStagesFixture.map((s) => ({ ...s }))
  store.product = seedBagProduct()
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.06)

function activeStage() {
  return store.stages.find((s) => s.id === store.subscription.activeStageId) ?? null
}

let helperSeq = 1000
function nextHelperId() {
  helperSeq += 1
  return `hf-${helperSeq}`
}

export const bagHandlers = [
  http.get('/api/v1/bag/product', async () => {
    await delay(280)
    if (maybeFail()) return errorResponse('Failed to load the bag product', 503)
    return envelope({ ...store.product })
  }),

  http.get('/api/v1/bag/history', async () => {
    await delay(300)
    if (maybeFail()) return errorResponse('Failed to load the bag history', 503)
    return envelope(seedBagHistory())
  }),

  http.get('/api/v1/bag/eligibility', async () => {
    await delay(300)
    if (maybeFail()) return errorResponse('Failed to load bag eligibility', 503)
    return envelope(seedEligibility())
  }),

  http.get('/api/v1/bag/subscription', async () => {
    await delay(320)
    if (maybeFail()) return errorResponse('Failed to load the bag subscription', 503)
    return envelope({ ...store.subscription })
  }),

  http.get('/api/v1/bag/stages', async () => {
    await delay(260)
    if (maybeFail()) return errorResponse('Failed to load the improvement stages', 503)
    return envelope(store.stages.map((s) => ({ ...s })))
  }),

  http.get('/api/v1/bag/entity-view', async () => {
    await delay(420)
    if (maybeFail()) return errorResponse('Failed to load the bag', 503)
    const stage = activeStage()
    const openGaps = store.gaps
      .filter((g) => g.open && g.stageId === store.subscription.activeStageId)
      .map((g) => ({
        ...g,
        consultantNote: g.consultantNote ? { ...g.consultantNote } : null,
        recommendedAction: g.recommendedAction ? { ...g.recommendedAction } : null,
        unmetPoints: g.unmetPoints.map((p) => ({ ...p })),
        helperFiles: g.helperFiles.map((f) => ({ ...f })),
      }))
    return envelope({
      subscription: { ...store.subscription },
      source: {
        kind: store.subscription.source,
        assessmentId: BAG_SOURCE_ASSESSMENT_ID,
        scorePercent: 78,
      },
      reference: seedReference(),
      stage: stage ? { ...stage } : null,
      gaps: openGaps,
    })
  }),

  http.get('/api/v1/bag/consultant-view', async () => {
    await delay(440)
    if (maybeFail()) return errorResponse('Failed to load the consultant bag view', 503)
    return envelope({
      subscription: { ...store.subscription },
      source: {
        kind: store.subscription.source,
        assessmentId: BAG_SOURCE_ASSESSMENT_ID,
        scorePercent: 78,
      },
      reference: seedReference(),
      tracks: seedAssessmentContext(),
      stages: store.stages.map((s) => ({ ...s })),
      gaps: store.gaps.map((g) => ({
        ...g,
        consultantNote: g.consultantNote ? { ...g.consultantNote } : null,
        recommendedAction: g.recommendedAction ? { ...g.recommendedAction } : null,
        unmetPoints: g.unmetPoints.map((p) => ({ ...p })),
        helperFiles: g.helperFiles.map((f) => ({ ...f })),
      })),
    })
  }),

  http.post('/api/v1/bag/open-stage-gaps', async ({ request }) => {
    await delay(500)
    if (maybeFail(0.07)) return errorResponse('Could not open the selected gaps', 500)
    const body = await request.json().catch(() => ({}))
    const ids = Array.isArray(body.gapIds) ? body.gapIds : []
    if (!ids.length) {
      return errorResponse('Select at least one gap to open for this stage', 422, {
        gapIds: ['Select at least one gap to open for this stage.'],
      })
    }
    const stageId = store.subscription.activeStageId
    let opened = 0
    store.gaps = store.gaps.map((g) => {
      if (ids.includes(g.id)) {
        opened += 1
        return { ...g, open: true, stageId }
      }
      return g
    })
    if (store.subscription.status === 'activated') store.subscription.status = 'stage-active'
    return envelope({ opened, stageId })
  }),

  http.post('/api/v1/bag/helper-files', async ({ request }) => {
    await delay(620)
    if (maybeFail(0.08)) return errorResponse('The file could not be attached', 500)
    const body = await request.json().catch(() => ({}))
    const gap = store.gaps.find((g) => g.id === body.gapId)
    if (!gap) return errorResponse('Gap not found', 404)
    const created = {
      id: nextHelperId(),
      gapId: gap.id,
      subCriterionId: gap.subCriterionId,
      guidingPointId: body.guidingPointId ?? null,
      kind: body.kind ?? 'guide',
      name: body.name ?? { ar: 'ملف مساعد', en: 'Helper file' },
      fileName: body.fileName ?? 'helper-file.pdf',
      sizeKb: Number(body.sizeKb) || 0,
      url: body.url ?? `/assets/bag/helpers/${body.fileName ?? 'helper-file.pdf'}`,
      attachedAt: new Date().toISOString(),
    }
    gap.helperFiles.push(created)
    return envelope({ ...created })
  }),

  http.post('/api/v1/bag/gap-guidance', async ({ request }) => {
    await delay(480)
    if (maybeFail(0.07)) return errorResponse('Could not save the gap guidance', 500)
    const body = await request.json().catch(() => ({}))
    const gap = store.gaps.find((g) => g.id === body.gapId)
    if (!gap) return errorResponse('Gap not found', 404)
    if (body.priority !== undefined) gap.priority = body.priority
    if (body.status !== undefined) gap.status = body.status
    if (body.consultantNote !== undefined) gap.consultantNote = body.consultantNote
    if (body.recommendedAction !== undefined) gap.recommendedAction = body.recommendedAction
    return envelope({
      id: gap.id,
      priority: gap.priority,
      status: gap.status,
      consultantNote: gap.consultantNote ? { ...gap.consultantNote } : null,
      recommendedAction: gap.recommendedAction ? { ...gap.recommendedAction } : null,
    })
  }),

  http.get('/api/v1/bag/reassessment', async () => {
    await delay(460)
    if (maybeFail()) return errorResponse('Failed to load the re-assessment comparison', 503)
    return envelope(buildReassessment())
  }),
]
