<template>
  <el-dialog
    v-model="dialogVisible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    destroy-on-close
    @close="handleClose"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :rules="rules"
      :label-width="labelWidth"
    >
      <el-form-item
        v-for="item in formItems"
        :key="item.prop"
        :label="item.label"
        :prop="item.prop"
      >
        <el-input
          v-if="item.type === 'input'"
          v-model="formData[item.prop]"
          :placeholder="item.placeholder || `请输入${item.label}`"
          :type="item.inputType || 'text'"
          :disabled="item.disabled"
          clearable
        />
        <el-input
          v-else-if="item.type === 'textarea'"
          v-model="formData[item.prop]"
          :placeholder="item.placeholder || `请输入${item.label}`"
          :rows="item.rows || 4"
          :disabled="item.disabled"
          type="textarea"
          clearable
        />
        <el-select
          v-else-if="item.type === 'select'"
          v-model="formData[item.prop]"
          :placeholder="item.placeholder || `请选择${item.label}`"
          :disabled="item.disabled"
          clearable
          style="width: 100%"
        >
          <el-option
            v-for="opt in item.options"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </el-select>
        <el-switch
          v-else-if="item.type === 'switch'"
          v-model="formData[item.prop]"
          :disabled="item.disabled"
        />
        <el-date-picker
          v-else-if="item.type === 'date'"
          v-model="formData[item.prop]"
          :type="item.dateType || 'date'"
          :placeholder="item.placeholder || `请选择${item.label}`"
          :disabled="item.disabled"
          style="width: 100%"
          value-format="YYYY-MM-DD"
        />
        <el-input-number
          v-else-if="item.type === 'number'"
          v-model="formData[item.prop]"
          :min="item.min"
          :max="item.max"
          :step="item.step || 1"
          :disabled="item.disabled"
          style="width: 100%"
        />
        <el-radio-group
          v-else-if="item.type === 'radio'"
          v-model="formData[item.prop]"
          :disabled="item.disabled"
        >
          <el-radio
            v-for="opt in item.options"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </el-radio>
        </el-radio-group>
        <el-checkbox-group
          v-else-if="item.type === 'checkbox'"
          v-model="formData[item.prop]"
          :disabled="item.disabled"
        >
          <el-checkbox
            v-for="opt in item.options"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </el-checkbox>
        </el-checkbox-group>
        <slot v-else-if="item.slot" :name="item.slot" :item="item" />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="handleClose">取 消</el-button>
        <el-button type="primary" :loading="confirmLoading" @click="handleConfirm">
          确 定
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  visible: {
    type: Boolean,
    default: false
  },
  formItems: {
    type: Array,
    default: () => []
  },
  formData: {
    type: Object,
    default: () => ({})
  },
  rules: {
    type: Object,
    default: () => ({})
  },
  width: {
    type: String,
    default: '500px'
  },
  labelWidth: {
    type: String,
    default: '100px'
  }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const formRef = ref(null)
const confirmLoading = ref(false)
const dialogVisible = ref(props.visible)

watch(() => props.visible, (val) => {
  dialogVisible.value = val
})

watch(dialogVisible, (val) => {
  emit('update:visible', val)
})

function handleClose() {
  dialogVisible.value = false
  formRef.value?.resetFields()
  emit('cancel')
}

async function handleConfirm() {
  if (!formRef.value) {
    emit('confirm', formData.value)
    return
  }

  try {
    await formRef.value.validate()
    confirmLoading.value = true
    emit('confirm', props.formData)
  } catch (e) {
    console.error('表单验证失败:', e)
  } finally {
    confirmLoading.value = false
  }
}

defineExpose({
  formRef
})
</script>

<style scoped lang="scss">
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
