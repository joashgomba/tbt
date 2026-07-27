<template>
  <div ref="mapEl" class="portal-map-wrap" role="img" :aria-label="ariaLabel"></div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  points: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: 'Map of reported barriers' },
  height: { type: String, default: '22rem' },
})

const mapEl = ref(null)
let map = null
let layer = null
let resizeObserver = null

const corridorCenter = [10.5, 42.0]
const corridorZoom = 6

onMounted(async () => {
  if (!mapEl.value) return
  map = L.map(mapEl.value, { scrollWheelZoom: false }).setView(corridorCenter, corridorZoom)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 18,
  }).addTo(map)
  renderMarkers()
  await refreshMapSize()

  resizeObserver = new ResizeObserver(() => refreshMapSize())
  resizeObserver.observe(mapEl.value)
})

watch(() => props.points, () => {
  renderMarkers()
  refreshMapSize()
}, { deep: true })

async function refreshMapSize() {
  if (!map) return
  await nextTick()
  map.invalidateSize({ animate: false })
  // Leaflet often needs a second pass after the tab/container finishes layout
  window.setTimeout(() => map?.invalidateSize({ animate: false }), 50)
  window.setTimeout(() => map?.invalidateSize({ animate: false }), 200)
}

function renderMarkers() {
  if (!map) return
  if (layer) {
    map.removeLayer(layer)
    layer = null
  }
  if (!props.points.length) return

  layer = L.layerGroup()
  const bounds = []

  props.points.forEach((p) => {
    if (!p.latitude || !p.longitude) return
    const lat = parseFloat(p.latitude)
    const lng = parseFloat(p.longitude)
    bounds.push([lat, lng])
    const radius = Math.min(28, 8 + (p.count || 1) * 3)
    const circle = L.circleMarker([lat, lng], {
      radius,
      fillColor: '#0b4f6c',
      color: '#fff',
      weight: 2,
      fillOpacity: 0.75,
    })
    circle.bindPopup(`<strong>${escapeHtml(p.label || p.country)}</strong><br>${p.count} report(s)`)
    layer.addLayer(circle)
  })

  layer.addTo(map)
  if (bounds.length === 1) {
    map.setView(bounds[0], 8)
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 8 })
  }
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

onUnmounted(() => {
  resizeObserver?.disconnect()
  if (map) {
    map.remove()
    map = null
  }
})

defineExpose({ refreshMapSize })
</script>

<style scoped>
.portal-map-wrap {
  height: v-bind(height);
  min-height: v-bind(height);
  width: 100%;
}

.portal-map-wrap :deep(.leaflet-container) {
  height: 100%;
  width: 100%;
}

.portal-map-wrap:empty {
  min-height: 12rem;
}
</style>
