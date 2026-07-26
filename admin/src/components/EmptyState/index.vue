<template>
  <div class="empty-state">
    <el-empty :description="description">
      <template #image>
        <slot name="image">
          <el-icon v-if="icon" class="empty-icon">
            <component :is="icon" />
          </el-icon>
        </slot>
      </template>
      <template #description>
        <slot name="description">
          <span class="empty-description">{{ description }}</span>
        </slot>
      </template>
      <slot />
    </el-empty>
  </div>
</template>

<script setup>
defineProps({
  description: {
    type: String,
    default: '暂无数据'
  },
  icon: {
    type: [String, Object],
    default: ''
  }
})
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.empty-state {
  padding: $spacing-xxxl 0;

  .empty-icon {
    font-size: 64px;
    color: $text-placeholder;
  }

  .empty-description {
    color: $text-secondary;
    font-size: $font-size-base;
  }
}
</style>
