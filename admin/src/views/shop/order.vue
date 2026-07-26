<template>
  <div class="shop-order">
    <div class="back-bar">
      <el-button type="text" @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
    </div>

    <div class="order-container" v-loading="loading">
      <h2 class="order-title">确认订单</h2>

      <el-card class="product-card" v-if="productInfo">
        <div class="product-info">
          <img :src="productInfo.image || getPlaceholder()" class="product-image" />
          <div class="product-detail">
            <h3 class="product-name">{{ productInfo.name }}</h3>
            <p class="product-desc">{{ productInfo.description }}</p>
            <div class="product-price">
              <span class="price">¥{{ productInfo.price }}</span>
              <span class="stock">库存: {{ productInfo.stock }}</span>
            </div>
          </div>
        </div>
      </el-card>

      <el-card class="form-card">
        <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
          <el-form-item label="购买数量">
            <el-input-number v-model="form.quantity" :min="1" :max="maxQuantity" @change="handleQuantityChange" />
            <span class="form-tip">每人限购 {{ maxQuantity }} 个</span>
          </el-form-item>

          <el-form-item label="收货邮箱" prop="email">
            <el-input v-model="form.email" placeholder="请输入邮箱，卡密将发送至此邮箱" />
          </el-form-item>

          <el-form-item label="支付方式">
            <el-radio-group v-model="form.pay_channel">
              <el-radio value="caihong">
                <div class="pay-option">
                  <span class="pay-name">彩虹易支付</span>
                </div>
              </el-radio>
            </el-radio-group>
          </el-form-item>

          <el-divider />

          <div class="order-summary">
            <div class="summary-item">
              <span>商品单价</span>
              <span>¥{{ productInfo?.price || '0.00' }}</span>
            </div>
            <div class="summary-item">
              <span>购买数量</span>
              <span>x {{ form.quantity }}</span>
            </div>
            <div class="summary-item total">
              <span>应付金额</span>
              <span class="total-price">¥{{ totalPrice }}</span>
            </div>
          </div>

          <div class="submit-section">
            <el-button type="primary" size="large" :loading="submitting" @click="submitOrder">
              立即支付
            </el-button>
          </div>
        </el-form>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const submitting = ref(false)
const formRef = ref(null)
const productInfo = ref(null)

const merchantNo = computed(() => route.params.merchantNo || '')
const productId = computed(() => route.query.product_id || '')

const form = reactive({
  quantity: 1,
  email: '',
  pay_channel: 'caihong'
})

const rules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }
  ]
}

const maxQuantity = computed(() => {
  if (!productInfo.value) return 1
  return Math.min(productInfo.value.stock, productInfo.value.per_user_limit || 10)
})

const totalPrice = computed(() => {
  if (!productInfo.value) return '0.00'
  return (productInfo.value.price * form.quantity).toFixed(2)
})

function getPlaceholder() {
  return `https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=${encodeURIComponent('product card digital product')}&image_size=square`
}

function handleQuantityChange(val) {
  if (val > maxQuantity.value) {
    form.quantity = maxQuantity.value
    ElMessage.warning(`每人限购 ${maxQuantity.value} 个`)
  }
}

async function fetchProduct() {
  if (!merchantNo.value || !productId.value) return
  loading.value = true
  try {
    const res = await axios.get(`/api/shop/${merchantNo.value}/products/${productId.value}`)
    if (res.data.code === 0) {
      productInfo.value = res.data.data
    }
  } catch (e) {
    ElMessage.error('获取商品信息失败')
  } finally {
    loading.value = false
  }
}

async function submitOrder() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    if (!productInfo.value) return

    submitting.value = true
    try {
      const res = await axios.post(`/api/shop/${merchantNo.value}/order/create`, {
        product_id: productInfo.value.id,
        quantity: form.quantity,
        email: form.email,
        pay_channel: form.pay_channel
      })

      if (res.data.code === 0) {
        const data = res.data.data
        if (data.pay_url) {
          window.location.href = data.pay_url
        } else {
          ElMessage.success('订单创建成功')
          router.push({
            path: `/shop/${merchantNo.value}/success`,
            query: {
              order_no: data.order_no,
              email: form.email
            }
          })
        }
      }
    } catch (e) {
      if (e.response && e.response.data) {
        ElMessage.error(e.response.data.message || '下单失败')
      } else {
        ElMessage.error('下单失败')
      }
    } finally {
      submitting.value = false
    }
  })
}

function goBack() {
  router.back()
}

onMounted(() => {
  fetchProduct()
})
</script>

<style scoped>
.shop-order {
  min-height: 100vh;
  background: #f5f5f5;
}

.back-bar {
  background: white;
  padding: 12px 20px;
  border-bottom: 1px solid #ebeef5;
}

.order-container {
  max-width: 700px;
  margin: 30px auto;
  padding: 0 20px;
}

.order-title {
  font-size: 22px;
  color: #303133;
  margin: 0 0 20px 0;
}

.product-card {
  margin-bottom: 20px;
}

.product-info {
  display: flex;
  gap: 20px;
}

.product-image {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 8px;
  background: #f5f7fa;
}

.product-detail {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.product-name {
  font-size: 18px;
  color: #303133;
  margin: 0 0 8px 0;
}

.product-desc {
  font-size: 14px;
  color: #606266;
  margin: 0 0 12px 0;
  flex: 1;
}

.product-price {
  display: flex;
  align-items: baseline;
  gap: 16px;
}

.price {
  font-size: 24px;
  font-weight: 600;
  color: #f56c6c;
}

.stock {
  font-size: 13px;
  color: #909399;
}

.form-card {
  margin-bottom: 30px;
}

.form-tip {
  color: #909399;
  font-size: 12px;
  margin-left: 12px;
}

.pay-option {
  display: flex;
  align-items: center;
}

.pay-name {
  font-size: 14px;
  color: #303133;
}

.order-summary {
  background: #fafafa;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  color: #606266;
  font-size: 14px;
}

.summary-item.total {
  padding-top: 12px;
  margin-top: 8px;
  border-top: 1px solid #ebeef5;
  font-size: 16px;
  color: #303133;
}

.total-price {
  font-size: 24px;
  font-weight: 600;
  color: #f56c6c;
}

.submit-section {
  text-align: center;
}

.submit-section .el-button {
  width: 100%;
  max-width: 300px;
}
</style>
