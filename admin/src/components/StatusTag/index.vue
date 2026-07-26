<template>
  <el-tag
    :type="tagType"
    :effect="effect"
    :size="size"
    :round="round"
    :closable="closable"
    @close="handleClose"
  >
    <slot>{{ text }}</slot>
  </el-tag>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: [String, Number],
    default: ''
  },
  text: {
    type: String,
    default: ''
  },
  type: {
    type: String,
    default: ''
  },
  effect: {
    type: String,
    default: 'light'
  },
  size: {
    type: String,
    default: 'small'
  },
  round: {
    type: Boolean,
    default: false
  },
  closable: {
    type: Boolean,
    default: false
  },
  statusMap: {
    type: Object,
    default: () => ({
      success: { type: 'success', text: '成功' },
      warning: { type: 'warning', text: '警告' },
      danger: { type: 'danger', text: '危险' },
      info: { type: 'info', text: '信息' },
      active: { type: 'success', text: '启用' },
      inactive: { type: 'info', text: '停用' },
      pending: { type: 'warning', text: '待处理' },
      processing: { type: 'primary', text: '处理中' },
      completed: { type: 'success', text: '已完成' },
      failed: { type: 'danger', text: '失败' },
      normal: { type: 'success', text: '正常' },
      banned: { type: 'danger', text: '已封禁' },
      unused: { type: 'info', text: '未激活' },
      used: { type: 'success', text: '已激活' },
      expired: { type: 'warning', text: '已过期' },
      voided: { type: 'info', text: '已作废' }
    })
  }
})

const emit = defineEmits(['close'])

const tagType = computed(() => {
  if (props.type) return props.type
  const statusKey = String(props.status).toLowerCase()
  if (props.statusMap[statusKey]) {
    return props.statusMap[statusKey].type
  }
  return 'info'
})

function handleClose() {
  emit('close')
}
</script>
