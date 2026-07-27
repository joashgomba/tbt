<template>
  <div class="loc-coord-picker">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
      <div>
        <div class="small fw-semibold">{{ t('locPickOnMap') }}</div>
        <div class="small text-muted">{{ t('locPickOnMapSub') }}</div>
      </div>
      <button
        v-if="hasCoordinates"
        type="button"
        class="btn btn-sm btn-outline-secondary"
        @click="clearCoordinates"
      >
        {{ t('locClearCoordinates') }}
      </button>
    </div>
    <div ref="mapEl" class="loc-map-panel loc-coord-picker-map" role="application" :aria-label="t('locPickOnMap')"></div>
    <div v-if="hasCoordinates" class="small text-muted mt-2">
      {{ t('corLatitude') }}: <strong>{{ displayLat }}</strong> ·
      {{ t('corLongitude') }}: <strong>{{ displayLng }}</strong>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useI18n } from '@/composables/useI18n'

const props = defineProps({
  latitude: { type: [Number, String, null], default: null },
  longitude: { type: [Number, String, null], default: null },
})

const emit = defineEmits(['update:latitude', 'update:longitude'])

const { t } = useI18n()

const mapEl = ref(null)
let map = null
let marker = null
let resizeObserver = null
let syncingFromMap = false

const corridorCenter = [10.5, 42.0]
const corridorZoom = 6
const pinIcon = L.divIcon({
  className: 'loc-coord-picker-marker',
  html: '<span aria-hidden="true"></span>',
  iconSize: [28, 28],
  iconAnchor: [14, 14],
})

const hasCoordinates = computed(() => isValidCoord(props.latitude) && isValidCoord(props.longitude))

const displayLat = computed(() => (hasCoordinates.value ? roundCoord(props.latitude) : '—'))
const displayLng = computed(() => (hasCoordinates.value ? roundCoord(props.longitude) : '—'))

function isValidCoord(v) {
  if (v === null || v === undefined || v === '') return false
  const n = Number(v)
  return Number.isFinite(n)
}

function roundCoord(v) {
  return Number(Number(v).toFixed(6))
}

function coordPair() {
  if (!hasCoordinates.value) return null
  return [roundCoord(props.latitude), roundCoord(props.longitude)]
}

function emitCoordinates(lat, lng) {
  syncingFromMap = true
  emit('update:latitude', roundCoord(lat))
  emit('update:longitude', roundCoord(lng))
  nextTick(() => { syncingFromMap = false })
}

function placeMarker(lat, lng, pan = true) {
  if (!map) return
  const pos = [lat, lng]
  if (marker) {
    marker.setLatLng(pos)
  } else {
    marker = L.marker(pos, { icon: pinIcon, draggable: true }).addTo(map)
    marker.on('dragend', () => {
      const { lat: mLat, lng: mLng } = marker.getLatLng()
      emitCoordinates(mLat, mLng)
    })
  }
  if (pan) map.setView(pos, Math.max(map.getZoom(), 10))
}

function removeMarker() {
  if (marker && map) {
    map.removeLayer(marker)
    marker = null
  }
}

function clearCoordinates() {
  removeMarker()
  emit('update:latitude', null)
  emit('update:longitude', null)
  map?.setView(corridorCenter, corridorZoom)
}

function syncMarkerFromProps() {
  if (!map || syncingFromMap) return
  const pair = coordPair()
  if (pair) {
    placeMarker(pair[0], pair[1], false)
  } else {
    removeMarker()
  }
}

onMounted(async () => {
  if (!mapEl.value) return
  map = L.map(mapEl.value, { scrollWheelZoom: true }).setView(corridorCenter, corridorZoom)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 18,
  }).addTo(map)

  map.on('click', (e) => {
    placeMarker(e.latlng.lat, e.latlng.lng, false)
    emitCoordinates(e.latlng.lat, e.latlng.lng)
  })

  syncMarkerFromProps()
  await refreshMapSize()
  resizeObserver = new ResizeObserver(() => refreshMapSize())
  resizeObserver.observe(mapEl.value)
})

watch(() => [props.latitude, props.longitude], () => {
  syncMarkerFromProps()
}, { deep: true })

onUnmounted(() => {
  resizeObserver?.disconnect()
  map?.remove()
  map = null
  marker = null
})

async function refreshMapSize() {
  if (!map) return
  await nextTick()
  map.invalidateSize({ animate: false })
  window.setTimeout(() => {
    map?.invalidateSize({ animate: false })
    const pair = coordPair()
    if (pair) {
      map?.setView(pair, Math.max(map.getZoom(), 10))
    }
  }, 100)
}

defineExpose({ refreshMapSize })
</script>
