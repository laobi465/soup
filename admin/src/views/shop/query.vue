<template>
  <div class="shop-query">
    <div class="back-bar">
      <el-button type="text" @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回首页
      </el-button>
    </div>

    <div class="query-container">
      <h2 class="query-title">订单查询</h2>

      <el-form :model="form" :rules="rules" ref="formRef" label-width="80px" class="query-form">
        <el-form-item label="订单号" prop="order_no">
          <el-input v-model="form.order_no" placeholder="请输入订单号" />
        </el-form-item>

        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" placeholder="请输入下单时的邮箱" />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleQuery" :loading="loading">查询</el-button>
        </el-form-item>
      </el-form>

      <div class="result" v-if="orderInfo">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="订单号">{{ orderInfo.order_no }}</el-descriptions-item>
          <el-descriptions-item label="订单类型">{{ orderInfo.type_text }}</el-descriptions-item>
          <el-descriptions-item label="支付金额">¥{{ orderInfo.amount }}</el-descriptions-item>
          <el-descriptions-item label="支付状态">
            <el-tag :type="getStatusTagType(orderInfo.pay_status)">
              {{ orderInfo.status_text }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="收货邮箱">{{ orderInfo.email }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ orderInfo.created_at }}</el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ orderInfo.pay_time || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div class="card-section" v-if="orderInfo.pay_status === 2 && orderInfo.card_info">
          <h3>卡密信息</h3>
          <div class="card-no">
            <el-input :value="orderInfo.card_info.card_no" readonly>
              <template #append>
                <el-button @click="copyCard">复制</el-button>
              </template>
            </el-input>
            <p class="card-tip">请妥善保管您的卡密</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const formRef = ref(null)
const orderInfo = ref(null)

const merchantNo = computed(() => route.params.merchantNo || '')

const form = reactive({
  order_no: '',
  email: ''
})

const rules = {
  order_no: [{ required: true, message: '请输入订单号', trigger: 'blur' }],
  email: [{ required: true, message: '请输入邮箱', trigger: 'blur' }]
}

function getStatusTagType(status) {
  const map = {
    1: 'warning',
    2: 'success',
    3: 'info',
    4: 'danger'
  }
  return map[status] || 'info'
}

async function handleQuery() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      const res = await axios.get('/api/shop/order/query', {
        params: form
      })
      if (res.data.code === 0) {
        orderInfo.value = res.data.data
      }
    } catch (e) {
      if (e.response && e.response.data) {
        ElMessage.error(e.response.data.message || '查询失败')
      } else {
        ElMessage.error('查询失败')
      }
    } finally {
      loading.value = false
    }
  })
}

function copyCard() {
  if (orderInfo.value && orderInfo.value.card_info && orderInfo.value.card_info.card_no) {
    navigator.clipboard.writeText(orderInfo.value.card_info.card_no).then(() => {
      ElMessage.success('卡密已复制')
    }).catch(() => {
      ElMessage.error('复制失败')
    })
  }
}

function goBack() {
  router.push(`/shop/${merchantNo.value}`)
}
</script>

<style scoped>
.shop-query {
  min-height: 100vh;
  background: #f5f5f5;
}

.back-bar {
  background: white;
  padding: 12px 20px;
  border-bottom: 1px solid #ebeef5;
}

.query-container {
  max-width: 600px;
  margin: 40px auto;
  background: white;
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.query-title {
  text-align: center;
  font-size: 24px;
  color: #303133;
  margin: 0 0 30px 0;
}

.query-form {
  margin-bottom: 30px;
}

.result {
  margin-top: 30px;
  padding-top: 30px;
  border-top: 1px solid #ebeef5;
}

.card-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.card-section h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px 0;
}

.card-tip {
  color: #909399;
  font-size: 12px;
  margin-top: 8px;
  text-align: center;
}
</style>
