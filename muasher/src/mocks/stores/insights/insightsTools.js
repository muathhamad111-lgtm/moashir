import { decimalWeight, displayEarned, displayPercent } from '../assessments/selfScoring'
import { boundTree } from '../assessments/assessmentsStore'
import { findEntity } from '../entitiesStore'
import { findFile } from '../finance/financeState'
import { signedDownloadOf } from '../finance/signedFiles'
import { bandOf, gapClassOf, insightsRules, rulesVersions } from './insightsRules'
import { interpretationOf } from './maturityTexts'
import { currentSummaryOf } from './assessorSummaries'
import { latestCertificateOf, previousOf, resultRefOf } from './insightsResults'

const TOP_COUNT = 3
const BANDS = ['emerging', 'developing', 'mature', 'leading']
const HUNDRED = 100

function fixed(value) {
  return (Math.round(Number(value) * HUNDRED) / HUNDRED).toFixed(2)
}

function signed(value) {
  const text = fixed(value)
  return Number(text) > 0 ? `+${text}` : text
}

function nodesOf(record) {
  const tree = boundTree(record)
  const mains = new Map(tree.mains.map((main) => [main.code, main]))
  return record.result.mains.map((main, mainIndex) => ({
    main,
    mainIndex,
    node: mains.get(main.code),
    subs: main.subs.map((sub, subIndex) => ({
      sub,
      subIndex,
      node: mains.get(main.code)?.subs.find((item) => item.code === sub.code),
    })),
  }))
}

function figures(node) {
  const weight = decimalWeight(node.weight)
  const earned = displayEarned(node.earned)
  return {
    weight,
    earned,
    percent: displayPercent(node.percent),
    lostPoints: fixed(Number(weight) - Number(earned)),
  }
}

function subItem(record, entry, subEntry) {
  const values = figures(subEntry.sub)
  const feedback = record.feedback?.[subEntry.sub.code] ?? {}
  return {
    mainCode: entry.main.code,
    mainName: { ...entry.node?.name },
    subCode: subEntry.sub.code,
    subName: { ...subEntry.node?.name },
    weight: values.weight,
    earned: values.earned,
    percent: values.percent,
    lostPoints: values.lostPoints,
    class: gapClassOf(values.percent),
    assessor: {
      strengths: feedback.strengths ?? null,
      improvementPoints: feedback.improvementPoints ?? null,
    },
    order: [entry.mainIndex, subEntry.subIndex],
  }
}

function byOrder(left, right) {
  return left.order[0] - right.order[0] || left.order[1] - right.order[1]
}

function byImpact(left, right) {
  return (
    Number(right.lostPoints) - Number(left.lostPoints) ||
    Number(left.percent) - Number(right.percent) ||
    byOrder(left, right)
  )
}

function byStrength(left, right) {
  return (
    Number(right.percent) - Number(left.percent) ||
    Number(right.weight) - Number(left.weight) ||
    byOrder(left, right)
  )
}

function withoutOrder(item) {
  const copy = { ...item }
  delete copy.order
  return copy
}

function classified(record) {
  const items = nodesOf(record).flatMap((entry) =>
    entry.subs.map((subEntry) => subItem(record, entry, subEntry)),
  )
  const ofClass = (gapClass, sort) =>
    items
      .filter((item) => item.class === gapClass)
      .sort(sort)
      .map(withoutOrder)
  return {
    strengths: ofClass('strength', byStrength),
    opportunities: ofClass('opportunity', byImpact),
    criticalGaps: ofClass('critical_gap', byImpact),
  }
}

function countsOf(items) {
  return {
    strength: items.strengths.length,
    opportunity: items.opportunities.length,
    criticalGap: items.criticalGaps.length,
  }
}

function withEffective(rules) {
  return { ...rules, effectiveSince: insightsRules().effectiveSince }
}

export function strengthsGapsOf(record) {
  const items = classified(record)
  return {
    result: resultRefOf(record),
    rules: withEffective(insightsRules().gapThresholds),
    counts: countsOf(items),
    mains: nodesOf(record).map((entry) => ({
      code: entry.main.code,
      name: { ...entry.node?.name },
      percent: displayPercent(entry.main.percent),
      class: gapClassOf(displayPercent(entry.main.percent)),
    })),
    items,
  }
}

function previousFigures(previous) {
  if (!previous) return null
  return new Map(
    nodesOf(previous).map((entry) => [
      entry.main.code,
      {
        entry,
        percent: displayPercent(entry.main.percent),
        subs: new Map(entry.subs.map((sub) => [sub.sub.code, displayPercent(sub.sub.percent)])),
      },
    ]),
  )
}

function deltaOf(current, previous) {
  return previous === undefined || previous === null ? null : signed(current - previous)
}

function maturitySub(subEntry, matched) {
  const percent = displayPercent(subEntry.sub.percent)
  return {
    code: subEntry.sub.code,
    name: { ...subEntry.node?.name },
    percent,
    band: bandOf(percent),
    deltaPp: matched ? deltaOf(percent, matched.subs.get(subEntry.sub.code)) : null,
  }
}

function comparisonOf(previousMap, matched) {
  if (!previousMap) return 'no_previous'
  return matched ? 'matched' : 'new_in_version'
}

function maturityMain(entry, previousMap) {
  const values = figures(entry.main)
  const band = bandOf(values.percent)
  const matched = previousMap?.get(entry.main.code) ?? null
  return {
    code: entry.main.code,
    name: { ...entry.node?.name },
    description: entry.node?.description ? { ...entry.node.description } : null,
    weight: values.weight,
    earned: values.earned,
    percent: values.percent,
    band,
    interpretation: interpretationOf(band, entry.main.code),
    comparison: comparisonOf(previousMap, matched),
    deltaPp: matched ? deltaOf(values.percent, matched.percent) : null,
    previousBand: matched ? bandOf(matched.percent) : null,
    subs: entry.subs.map((subEntry) => maturitySub(subEntry, matched)),
  }
}

function removedSince(record, previousMap) {
  if (!previousMap) return []
  const codes = new Set(record.result.mains.map((main) => main.code))
  return [...previousMap.values()]
    .filter((item) => !codes.has(item.entry.main.code))
    .map((item) => ({
      code: item.entry.main.code,
      name: { ...item.entry.node?.name },
      previousPercent: item.percent,
    }))
}

function totalDelta(record, previous) {
  if (!previous) return null
  return deltaOf(displayPercent(record.result.percent), displayPercent(previous.result.percent))
}

export function maturityOf(record) {
  const previous = previousOf(record)
  const previousMap = previousFigures(previous)
  const percent = displayPercent(record.result.percent)
  return {
    result: resultRefOf(record),
    previous: previous ? resultRefOf(previous) : null,
    versionsDiffer: Boolean(previous) && previous.criteriaVersionId !== record.criteriaVersionId,
    rules: withEffective(insightsRules().maturityBands),
    overall: {
      percent,
      band: bandOf(percent),
      deltaPp: totalDelta(record, previous),
      previousBand: previous ? bandOf(displayPercent(previous.result.percent)) : null,
    },
    mains: nodesOf(record).map((entry) => maturityMain(entry, previousMap)),
    removedSincePrevious: removedSince(record, previousMap),
  }
}

function standingOf(record) {
  const certificate = latestCertificateOf(record)
  if (!certificate || certificate.status !== 'issued')
    return { standing: 'not_issued', level: null }
  return certificate.level
    ? { standing: 'level', level: certificate.level }
    : { standing: 'participation', level: null }
}

function bandCounts(mains) {
  return Object.fromEntries(
    BANDS.map((band) => [band, mains.filter((main) => main.band === band).length]),
  )
}

function quoted(item, field) {
  return { ...item, quote: item.assessor[field] }
}

function logoUrlOf(entity, viewer) {
  const file = entity?.logo ? findFile(entity.logo.id) : null
  return file && viewer ? signedDownloadOf(viewer, file).url : null
}

export function executiveSummaryOf(record, viewer = null) {
  const items = classified(record)
  const maturity = maturityOf(record)
  const previous = previousOf(record)
  const summary = currentSummaryOf(record.id)
  const entity = findEntity(record.entityId)
  return {
    result: resultRefOf(record),
    entity: {
      name: { ...entity?.name },
      hasLogo: Boolean(entity?.logo),
      logoUrl: logoUrlOf(entity, viewer),
    },
    score: {
      percent: displayPercent(record.result.percent),
      earned: displayEarned(record.result.total),
      totalWeight: decimalWeight(record.result.totalWeight),
    },
    certificate: standingOf(record),
    change: {
      deltaPp: maturity.overall.deltaPp,
      previous: previous ? resultRefOf(previous) : null,
      versionsDiffer: maturity.versionsDiffer,
    },
    assessorSummary: summary ? { body: summary.body, version: summary.version } : null,
    profile: { classes: countsOf(items), bands: bandCounts(maturity.mains) },
    strongAreas: items.strengths.slice(0, TOP_COUNT).map((item) => quoted(item, 'strengths')),
    priorityGaps: [...items.criticalGaps, ...items.opportunities]
      .slice(0, TOP_COUNT)
      .map((item) => quoted(item, 'improvementPoints')),
    rules: {
      ...rulesVersions(),
      gapThresholds: { ...insightsRules().gapThresholds },
      maturityBands: { ...insightsRules().maturityBands },
    },
  }
}
