<template>
  <div class="shop-success">
    <div class="success-container">
      <div class="success-icon">
        <el-icon :size="80" color="#67c23a"><CircleCheckFilled /></el-icon>
      </div>
      <h1 class="success-title">支付成功</h1>
      <p class="success-desc">您的订单已支付成功，卡密已发送至您的邮箱</p>

      <div class="order-info" v-if="orderInfo">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="订单号">{{ orderInfo.order_no }}</el-descriptions-item>
          <el-descriptions-item label="商品名称">{{ orderInfo.type_text }}</el-descriptions-item>
          <el-descriptions-item label="支付金额">¥{{ orderInfo.amount }}</el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ orderInfo.pay_time }}</el-descriptions-item>
          <el-descriptions-item label="收货邮箱">{{ orderInfo.email }}</el-descriptions-item>
        </el-descriptions>

        <div class="card-info" v-if="cardInfo">
          <h3>卡密信息</h3>
          <div class="card-no">
            <span>卡密：</span>
            <el-input :value="cardInfo.card_no" readonly>
              <template #append>
                <el-button @click="copyCard">复制</el-button>
              </template>
            </el-input>
          </div>
        </div>
      </div>

      <div class="loading" v-else>
        <el-icon class="is-loading" :size="40"><Loading /></el-icon>
        <p>正在查询订单状态...</p>
      </div>

      <div class="actions">
        <el-button type="primary" @click="goBack">返回首页</el-button>
        <el-button @click="goQuery">查询订单</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CircleCheckFilled, Loading } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const orderNo = computed(() => route.query.order_no || '')
const email = computed(() => route.query.email || '')
const merchantNo = computed(() => route.params.merchantNo || '')

const orderInfo = ref(null)
const cardInfo = ref(null)

async function queryOrder() {
  if (!orderNo.value || !email.value) return

  try {
    const res = await axios.get('/api/shop/order/query', {
      params: {
        order_no: orderNo.value,
        email: email.value
      }
    })

    if (res.data.code === 0) {
      orderInfo.value = res.data.data
      if (res.data.data.card_info) {
        cardInfo.value = res.data.data.card_info
      }
    }
  } catch (e) {
    console.error(e)
  }
}

function copyCard() {
  if (cardInfo.value && cardInfo.value.card_no) {
    navigator.clipboard.writeText(cardInfo.value.card_no).then(() => {
      ElMessage.success('卡密已复制')
    }).catch(() => {
      ElMessage.error('复制失败')
    })
  }
}

function goBack() {
  router.push(`/shop/${merchantNo.value}`)
}

function goQuery() {
  router.push(`/shop/${merchantNo.value}/query`)
}

onMounted(() => {
  queryOrder()
  const timer = setInterval(() => {
    if (!orderInfo.value || orderInfo.value.pay_status !== 2) {
      queryOrder()
    } else {
      clearInterval(timer)
    }
  }, 3000)
})
</script>

<style scoped>
.shop-success {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.success-container {
  background: white;
  border-radius: 12px;
  padding: 40px;
  text-align: center;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.success-icon {
  margin-bottom: 20px;
}

.success-title {
  font-size: 24px;
  color: #303133;
  margin: 0 0 8px 0;
}

.success-desc {
  color: #909399;
  margin: 0 0 30px 0;
  font-size: 14px;
}

.order-info {
  text-align: left;
  margin-bottom: 30px;
}

.card-info {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.card-info h3 {
  font-size: 16px;
  color: #303133;
  margin: 0 0 16px 0;
}

.card-no {
  margin-top: 12px;
}

.loading {
  padding: 40px 0;
  color: #909399;
}

.loading .el-icon {
  margin-bottom: 12px;
}

.actions {
  display: flex;
  gap: 16px;
  justify-content: center;
}

.actions .el-button {
  min-width: 120px;
}
</style>
