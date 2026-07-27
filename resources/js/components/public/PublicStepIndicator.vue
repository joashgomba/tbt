<template>
  <nav class="portal-step-indicator" :aria-label="ariaLabel">
    <div
      v-for="(step, index) in steps"
      :key="step.key"
      class="portal-step-indicator__item"
      :class="{
        'portal-step-indicator__item--active': current === index + 1,
        'portal-step-indicator__item--done': current > index + 1,
      }"
      :aria-current="current === index + 1 ? 'step' : undefined"
    >
      <span class="portal-step-indicator__dot" :aria-hidden="true">
        <i v-if="current > index + 1" class="ti ti-check"></i>
        <span v-else>{{ index + 1 }}</span>
      </span>
      <span class="portal-step-indicator__label">{{ step.label }}</span>
    </div>
  </nav>
</template>

<script setup>
defineProps({
  steps: { type: Array, required: true },
  current: { type: Number, required: true },
  ariaLabel: { type: String, default: 'Progress' },
})
</script>
