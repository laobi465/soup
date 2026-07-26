<template>
  <span class="copy-text" @click="handleCopy">
    <span class="copy-content">
      <slot>{{ text }}</slot>
    </span>
    <el-tooltip :content="copied ? '已复制' : '点击复制'" placement="top">
      <el-icon class="copy-icon">
        <CopyDocument v-if="!copied" />
        <Check v-else />
      </el-icon>
    </el-tooltip>
  </span>
</template>

<script setup>
import { ref } from 'vue'
import { CopyDocument, Check } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const props = defineProps({
  text: {
    type: String,
    default: ''
  },
  showMessage: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['copy'])

const copied = ref(false)

async function handleCopy() {
  const textToCopy = props.text || (document.querySelector('.copy-content')?.textContent || '')
  
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(textToCopy)
    } else {
      const textarea = document.createElement('textarea')
      textarea.value = textToCopy
      textarea.style.position = 'fixed'
      textarea.style.opacity = '0'
      document.body.appendChild(textarea)
      textarea.select()
      document.execCommand('copy')
      document.body.removeChild(textarea)
    }
    
    copied.value = true
    emit('copy', textToCopy)
    
    if (props.showMessage) {
      ElMessage.success('复制成功')
    }
    
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (e) {
    console.error('复制失败:', e)
    if (props.showMessage) {
      ElMessage.error('复制失败')
    }
  }
}
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.copy-text {
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  gap: 4px;

  .copy-content {
    color: $text-regular;
  }

  .copy-icon {
    color: $text-secondary;
    font-size: $font-size-sm;
    transition: color 0.2s;
  }

  &:hover {
    .copy-icon {
      color: $primary-color;
    }
  }
}
</style>
