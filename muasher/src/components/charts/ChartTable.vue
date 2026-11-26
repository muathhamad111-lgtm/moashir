<script setup>
defineProps({
  caption: { type: String, required: true },
  columns: { type: Array, required: true },
  rows: { type: Array, required: true },
})
</script>

<template>
  <!-- A table ignores a 1px box, so its hidden wrapper is what clips it. -->
  <div class="chart-table">
    <table>
      <caption>
        {{
          caption
        }}
      </caption>
      <thead>
        <tr>
          <th v-for="column in columns" :key="column" scope="col">{{ column }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row[0]">
          <th scope="row">{{ row[0] }}</th>
          <td v-for="(cell, index) in row.slice(1)" :key="index">{{ cell }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
