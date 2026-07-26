<template>
  <span class="count-down">
    <slot :time="timeText" :seconds="remainingSeconds">
      {{ timeText }}
    </slot>
  </span>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  seconds: {
    type: Number,
    default: 0
  },
  endTime: {
    type: [Number, String],
    default: ''
  },
  format: {
    type: String,
    default: 'HH:mm:ss'
  },
  autoStart: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['finish', 'tick'])

const remainingSeconds = ref(0)
let timer = null

const timeText = computed(() => {
  const total = Math.max(0, remainingSeconds.value)
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const seconds = total % 60

  const pad = (n) => String(n).padStart(2, '0')

  return props.format
    .replace('HH', pad(hours))
    .replace('mm', pad(minutes))
    .replace('ss', pad(seconds))
    .replace('D', String(Math.floor(hours / 24)))
})

function start() {
  if (timer) return

  if (props.endTime) {
    const end = new Date(props.endTime).getTime()
    const now = Date.now()
    remainingSeconds.value = Math.max(0, Math.floor((end - now) / 1000))
  } else {
    remainingSeconds.value = props.seconds
  }

  timer = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
      emit('tick', remainingSeconds.value)
    } else {
      stop()
      emit('finish')
    }
  }, 1000)
}

function stop() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

function reset() {
  stop()
  if (props.endTime) {
    const end = new Date(props.endTime).getTime()
    const now = Date.now()
    remainingSeconds.value = Math.max(0, Math.floor((end - now) / 1000))
  } else {
    remainingSeconds.value = props.seconds
  }
  if (props.autoStart) {
    start()
  }
}

watch(() => props.seconds, () => {
  reset()
})

watch(() => props.endTime, () => {
  reset()
})

onMounted(() => {
  if (props.autoStart) {
    start()
  }
})

onUnmounted(() => {
  stop()
})

defineExpose({
  start,
  stop,
  reset,
  remainingSeconds
})
</script>

<style scoped lang="scss">
.count-down {
  font-variant-numeric: tabular-nums;
}
</style>
