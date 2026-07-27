<template>
  <div ref="mapEl" class="loc-map-panel" role="img" :aria-label="ariaLabel"></div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  points: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: 'Location map' },
  selectedId: { type: Number, default: null },
})

const emit = defineEmits(['select'])

const mapEl = ref(null)
let map = null
let layer = null
let resizeObserver = null

const corridorCenter = [10.5, 42.0]
const corridorZoom = 6

onMounted(async () => {
  if (!mapEl.value) return
  map = L.map(mapEl.value, { scrollWheelZoom: true }).setView(corridorCenter, corridorZoom)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 18,
  }).addTo(map)
  renderMarkers()
  await refreshMapSize()
  resizeObserver = new ResizeObserver(() => refreshMapSize())
  resizeObserver.observe(mapEl.value)
})

watch(() => [props.points, props.selectedId], () => {
  renderMarkers()
  refreshMapSize()
}, { deep: true })

onUnmounted(() => {
  resizeObserver?.disconnect()
  map?.remove()
})

async function refreshMapSize() {
  if (!map) return
  await nextTick()
  map.invalidateSize({ animate: false })
  window.setTimeout(() => map?.invalidateSize({ animate: false }), 100)
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
    if (p.latitude == null || p.longitude == null) return
    const lat = parseFloat(p.latitude)
    const lng = parseFloat(p.longitude)
    bounds.push([lat, lng])
    const selected = props.selectedId === p.id
    const color = p.border_post ? '#dc2626' : '#0891b2'
    const circle = L.circleMarker([lat, lng], {
      radius: selected ? 12 : 9,
      fillColor: color,
      color: selected ? '#164e63' : '#fff',
      weight: selected ? 3 : 2,
      fillOpacity: 0.85,
    })
    circle.on('click', () => emit('select', p))
    circle.bindPopup(`<strong>${escapeHtml(p.label || p.location_name)}</strong><br>${escapeHtml(p.location_type || '')}${p.open_cases != null ? `<br>${p.open_cases} open case(s)` : ''}`)
    layer.addLayer(circle)
  })

  layer.addTo(map)
  if (bounds.length === 1) {
    map.setView(bounds[0], 10)
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [24, 24], maxZoom: 10 })
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

defineExpose({ refreshMapSize })
</script>
