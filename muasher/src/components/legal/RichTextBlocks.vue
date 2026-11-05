<script setup>
import { RICH_TEXT_BLOCKS } from '@/constants/legal'

const MAX_HEADING = 6

const props = defineProps({
  blocks: { type: Array, required: true },
  headingBase: { type: Number, default: 2 },
})

function headingTag(level) {
  const value = Math.min(
    MAX_HEADING,
    Math.max(props.headingBase, Number(level) || props.headingBase),
  )
  return `h${value}`
}

function keyOf(block, index) {
  return `${index}-${block.type}`
}
</script>

<template>
  <div class="rich-text">
    <template v-for="(block, index) in blocks" :key="keyOf(block, index)">
      <component
        :is="headingTag(block.level)"
        v-if="block.type === RICH_TEXT_BLOCKS.HEADING"
        class="rich-text__heading"
        >{{ block.text }}</component
      >
      <p v-else-if="block.type === RICH_TEXT_BLOCKS.PARAGRAPH" class="rich-text__paragraph">
        {{ block.text }}
      </p>
      <ol
        v-else-if="block.type === RICH_TEXT_BLOCKS.LIST && block.ordered"
        class="rich-text__list rich-text__list--ordered"
        :start="block.start ?? undefined"
      >
        <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
      </ol>
      <ul v-else-if="block.type === RICH_TEXT_BLOCKS.LIST" class="rich-text__list">
        <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
      </ul>
      <div
        v-else-if="block.type === RICH_TEXT_BLOCKS.TABLE"
        class="rich-text__table-wrap"
        tabindex="0"
      >
        <table class="rich-text__table">
          <thead>
            <tr>
              <th v-for="(cell, cellIndex) in block.header" :key="cellIndex" scope="col">
                {{ cell }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, rowIndex) in block.rows" :key="rowIndex">
              <td v-for="(cell, cellIndex) in row" :key="cellIndex">{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
