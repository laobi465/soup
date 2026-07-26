<template>
  <el-dialog
    v-model="dialogVisible"
    :title="title"
    width="420px"
    :close-on-click-modal="false"
    :show-close="false"
    @close="handleClose"
  >
    <div class="confirm-content">
      <el-icon :class="['confirm-icon', type]">
        <Warning v-if="type === 'warning'" />
        <CircleClose v-else-if="type === 'danger'" />
        <InfoFilled v-else />
      </el-icon>
      <div class="confirm-text">
        <slot name="content">
          {{ content }}
        </slot>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="handleCancel">取 消</el-button>
        <el-button :type="type === 'danger' ? 'danger' : 'primary'" @click="handleConfirm">
          确 定
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Warning, CircleClose, InfoFilled } from '@element-plus/icons-vue'

const props = defineProps({
  title: {
    type: String,
    default: '提示'
  },
  content: {
    type: String,
    default: ''
  },
  type: {
    type: String,
    default: 'warning',
    validator: (val) => ['warning', 'danger', 'info'].includes(val)
  },
  visible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const dialogVisible = ref(props.visible)

watch(() => props.visible, (val) => {
  dialogVisible.value = val
})

watch(dialogVisible, (val) => {
  emit('update:visible', val)
})

function handleClose() {
  dialogVisible.value = false
}

function handleCancel() {
  dialogVisible.value = false
  emit('cancel')
}

function handleConfirm() {
  dialogVisible.value = false
  emit('confirm')
}
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.confirm-content {
  display: flex;
  align-items: flex-start;
  padding: $spacing-sm 0;
}

.confirm-icon {
  font-size: 24px;
  margin-right: $spacing-base;
  flex-shrink: 0;
  margin-top: 2px;

  &.warning {
    color: $warning-color;
  }

  &.danger {
    color: $danger-color;
  }

  &.info {
    color: $primary-color;
  }
}

.confirm-text {
  flex: 1;
  font-size: $font-size-base;
  color: $text-regular;
  line-height: 1.6;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
