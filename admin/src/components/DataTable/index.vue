<template>
  <div class="data-table">
    <div class="table-wrapper">
      <el-table
        v-loading="loading"
        :data="data"
        :border="false"
        stripe
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column
          v-if="showSelection"
          type="selection"
          width="55"
          align="center"
        />
        <el-table-column
          v-if="showIndex"
          type="index"
          label="序号"
          width="60"
          align="center"
        />
        <el-table-column
          v-for="col in columns"
          :key="col.prop || col.label"
          :prop="col.prop"
          :label="col.label"
          :width="col.width"
          :min-width="col.minWidth"
          :fixed="col.fixed"
          :align="col.align || 'left'"
          :show-overflow-tooltip="col.tooltip !== false"
        >
          <template #default="scope">
            <slot v-if="col.slotName" :name="col.slotName" :row="scope.row" :index="scope.$index" />
            <span v-else>{{ scope.row[col.prop] }}</span>
          </template>
        </el-table-column>
        <el-table-column
          v-if="$slots.action"
          label="操作"
          :width="actionWidth"
          fixed="right"
          align="center"
        >
          <template #default="scope">
            <slot name="action" :row="scope.row" :index="scope.$index" />
          </template>
        </el-table-column>
      </el-table>
    </div>

    <div v-if="showPagination" class="pagination-wrapper">
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="currentPageSize"
        :page-sizes="pageSizes"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  columns: {
    type: Array,
    default: () => []
  },
  data: {
    type: Array,
    default: () => []
  },
  total: {
    type: Number,
    default: 0
  },
  page: {
    type: Number,
    default: 1
  },
  pageSize: {
    type: Number,
    default: 20
  },
  loading: {
    type: Boolean,
    default: false
  },
  showSelection: {
    type: Boolean,
    default: false
  },
  showIndex: {
    type: Boolean,
    default: false
  },
  showPagination: {
    type: Boolean,
    default: true
  },
  pageSizes: {
    type: Array,
    default: () => [10, 20, 50, 100]
  },
  actionWidth: {
    type: [String, Number],
    default: 180
  }
})

const emit = defineEmits(['update:page', 'update:pageSize', 'search', 'selection-change'])

const currentPage = ref(props.page)
const currentPageSize = ref(props.pageSize)

watch(() => props.page, (val) => {
  currentPage.value = val
})

watch(() => props.pageSize, (val) => {
  currentPageSize.value = val
})

function handleSizeChange(size) {
  currentPageSize.value = size
  emit('update:pageSize', size)
  emit('search', { page: 1, pageSize: size })
}

function handleCurrentChange(page) {
  currentPage.value = page
  emit('update:page', page)
  emit('search', { page, pageSize: currentPageSize.value })
}

function handleSelectionChange(selection) {
  emit('selection-change', selection)
}
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.data-table {
  .table-wrapper {
    background-color: $background-base;
    border-radius: $border-radius-base;
    overflow: hidden;
  }

  .pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    padding-top: $spacing-base;
  }
}

@media screen and (max-width: $breakpoint-sm) {
  .data-table {
    .table-wrapper {
      overflow-x: auto;
    }
  }
}
</style>
