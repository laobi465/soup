<template>
  <div class="shop-product-detail">
    <div class="back-bar">
      <el-button type="text" @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回列表
      </el-button>
    </div>

    <div class="product-container" v-loading="loading">
      <div class="product-image">
        <img :src="product.image || defaultImage" :alt="product.name" />
      </div>

      <div class="product-info">
        <h1 class="product-name">{{ product.name }}</h1>
        <div class="product-category" v-if="product.category">
          <el-tag size="small">{{ product.category }}</el-tag>
        </div>
        <div class="product-price">
          <span class="price-symbol">¥</span>
          <span class="price-value">{{ product.price }}</span>
        </div>
        <div class="product-stock">
          库存：<span :class="{ 'low-stock': product.stock <= 10 }">{{ product.stock }} 件</span>
        </div>
        <div class="product-desc" v-if="product.description">
          <div class="desc-title">商品描述</div>
          <div class="desc-content">{{ product.description }}</div>
        </div>

        <div class="purchase-section">
          <div class="quantity-row">
            <span class="label">购买数量</span>
            <el-input-number v-model="quantity" :min="1" :max="maxQuantity" />
          </div>
          <div class="email-row">
            <span class="label">收货邮箱</span>
            <el-input v-model="email" placeholder="请输入邮箱，用于接收卡密" />
          </div>
          <div class="total-row">
            <span class="label">合计</span>
            <span class="total-price">¥{{ totalPrice }}</span>
          </div>
          <el-button
            type="primary"
            size="large"
            class="buy-btn"
            :loading="purchasing"
            :disabled="product.stock <= 0"
            @click="handleBuy"
          >
            {{ product.stock <= 0 ? '暂无库存' : '立即购买' }}
          </el-button>
        </div>
      </div>
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
const purchasing = ref(false)
const quantity = ref(1)
const email = ref('')

const merchantNo = computed(() => route.params.merchantNo || '')
const productId = computed(() => route.params.id || '')

const product = reactive({
  id: 0,
  name: '',
  image: '',
  description: '',
  price: 0,
  stock: 0,
  category: '',
  limit_per_user: 0,
  limit_per_ip: 0,
  limit_per_device: 0
})

const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjY2NjIiBmb250LXNpemU9IjE4Ij7llJrml6Dlm77niYg8L3RleHQ+PC9zdmc+'

const maxQuantity = computed(() => {
  if (product.limit_per_user > 0) {
    return Math.min(product.stock, product.limit_per_user)
  }
  return product.stock
})

const totalPrice = computed(() => {
  return (product.price * quantity.value).toFixed(2)
})

async function fetchProduct() {
  loading.value = true
  try {
    const res = await axios.get(`/api/shop/${merchantNo.value}/products/${productId.value}`)
    if (res.data.code === 0) {
      Object.assign(product, res.data.data)
    }
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push(`/shop/${merchantNo.value}`)
}

async function handleBuy() {
  if (!email.value) {
    ElMessage.warning('请输入收货邮箱')
    return
  }
  if (!/^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/.test(email.value)) {
    ElMessage.warning('邮箱格式不正确')
    return
  }

  purchasing.value = true
  try {
    const res = await axios.post(`/api/shop/${merchantNo.value}/order/create`, {
      product_id: product.id,
      quantity: quantity.value,
      email: email.value,
      pay_channel: 'caihong',
      pay_type: 'alipay'
    })

    if (res.data.code === 0) {
      const data = res.data.data
      if (data.pay_url) {
        ElMessage.success('订单创建成功，正在跳转支付...')
        setTimeout(() => {
          window.location.href = data.pay_url
        }, 1000)
      } else {
        ElMessage.success('订单创建成功')
        router.push(`/shop/${merchantNo.value}/success?order_no=${data.order_no}&email=${email.value}`)
      }
    } else {
      ElMessage.error(res.data.message || '下单失败')
    }
  } finally {
    purchasing.value = false
  }
}

onMounted(() => {
  fetchProduct()
})
</script>

<style scoped>
.shop-product-detail {
  min-height: 100vh;
  background: #f5f5f5;
  padding-bottom: 60px;
}

.back-bar {
  background: white;
  padding: 12px 20px;
  border-bottom: 1px solid #ebeef5;
}

.product-container {
  max-width: 1000px;
  margin: 20px auto;
  background: white;
  border-radius: 8px;
  padding: 30px;
  display: flex;
  gap: 40px;
}

.product-image {
  width: 400px;
  flex-shrink: 0;
}

.product-image img {
  width: 100%;
  height: 400px;
  object-fit: cover;
  border-radius: 8px;
  background: #f5f5f5;
}

.product-info {
  flex: 1;
}

.product-name {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 12px 0;
}

.product-category {
  margin-bottom: 16px;
}

.product-price {
  color: #f56c6c;
  margin-bottom: 16px;
}

.product-price .price-symbol {
  font-size: 16px;
}

.product-price .price-value {
  font-size: 36px;
  font-weight: 600;
}

.product-stock {
  font-size: 14px;
  color: #606266;
  margin-bottom: 20px;
}

.product-stock .low-stock {
  color: #f56c6c;
  font-weight: 500;
}

.product-desc {
  margin-bottom: 30px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.desc-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 12px;
}

.desc-content {
  color: #606266;
  line-height: 1.8;
  font-size: 14px;
  white-space: pre-wrap;
}

.purchase-section {
  background: #fafafa;
  border-radius: 8px;
  padding: 24px;
}

.quantity-row,
.email-row,
.total-row {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
}

.quantity-row .label,
.email-row .label,
.total-row .label {
  width: 80px;
  color: #606266;
  font-size: 14px;
}

.total-price {
  color: #f56c6c;
  font-size: 24px;
  font-weight: 600;
}

.buy-btn {
  width: 100%;
  margin-top: 10px;
  height: 48px;
  font-size: 16px;
}

@media (max-width: 768px) {
  .product-container {
    flex-direction: column;
    padding: 20px;
  }

  .product-image {
    width: 100%;
  }

  .product-image img {
    height: 300px;
  }
}
</style>
