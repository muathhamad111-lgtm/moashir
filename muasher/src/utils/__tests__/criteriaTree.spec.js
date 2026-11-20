import { describe, expect, it } from 'vitest'
import { ancestorsOf, indexTree, nodeKey, parseNodeKey } from '@/utils/criteriaTree'
import { workspaceTree } from '@/components/criteria/workspace/__tests__/criteriaTreeFixture'

describe('criteriaTree', () => {
  it('builds and parses node keys', () => {
    expect(nodeKey('sub', 21)).toBe('sub-21')
    expect(parseNodeKey('point-31')).toEqual({ type: 'point', id: 31 })
    expect(parseNodeKey('version-4')).toBeNull()
  })

  it('indexes every node with its parent and children', () => {
    const index = indexTree(workspaceTree())
    expect(index.get('main-11').children).toEqual(['sub-21', 'sub-22'])
    expect(index.get('sub-21').children).toEqual(['point-31'])
    expect(index.get('point-31').parentKey).toBe('sub-21')
    expect(ancestorsOf(index, 'point-31')).toEqual(['main-11', 'sub-21'])
  })

  it('flags active nodes whose children do not add up, never inactive or direct ones', () => {
    const index = indexTree(workspaceTree())
    expect(index.get('sub-21').attention).toBe(true)
    expect(index.get('sub-22').attention).toBe(false)
    expect(index.get('main-12').attention).toBe(false)
  })
})
