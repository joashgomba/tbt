<template>
  <div class="card border-0 shadow-sm"><div class="card-body">
    <h5>{{ title }}</h5>
    <p class="text-muted mb-0">Module available in the administrative portal.</p>
  </div></div>
</template>

<script setup>
defineProps({ title: { type: String, default: 'Module' } })
</script>
