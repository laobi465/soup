<template>
  <div class="shop-home">
    <div class="shop-header">
      <div class="shop-info">
        <div class="shop-name">{{ shopInfo.shop_name }}</div>
        <div class="shop-notice" v-if="shopInfo.shop_notice">{{ shopInfo.shop_notice }}</div>
      </div>
    </div>

    <div class="shop-content">
      <div class="search-bar">
        <el-input v-model="keyword" placeholder="搜索商品..." clearable @keyup.enter="searchProducts">
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-select v-model="category" placeholder="全部分类" clearable style="margin-left: 12px; width: 140px;" @change="searchProducts">
          <el-option
            v-for="cat in categories"
            :key="cat"
            :label="cat"
            :value="cat"
          />
        </el-select>
      </div>

      <div class="product-grid" v-loading="loading">
        <div
          v-for="product in products"
          :key="product.id"
          class="product-card"
          @click="goDetail(product)"
        >
          <div class="product-image">
            <img :src="product.image || defaultImage" :alt="product.name" />
            <div class="product-stock" v-if="product.stock <= 10">
              库存: {{ product.stock }}
            </div>
          </div>
          <div class="product-info">
            <div class="product-name">{{ product.name }}</div>
            <div class="product-category" v-if="product.category">{{ product.category }}</div>
            <div class="product-price">
              <span class="price-symbol">¥</span>
              <span class="price-value">{{ product.price }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="pagination" v-if="total > 0">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchProducts"
        />
      </div>

      <el-empty v-if="!loading && products.length === 0" description="暂无商品" />
    </div>

    <div class="shop-footer">
      <div class="footer-links">
        <el-button type="text" @click="goQuery">订单查询</el-button>
      </div>
      <div class="footer-contact" v-if="shopInfo.contact_info">
        {{ shopInfo.contact_info }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const merchantNo = computed(() => route.params.merchantNo || '')
const loading = ref(false)
const keyword = ref('')
const category = ref('')
const products = ref([])
const categories = ref([])

const shopInfo = reactive({
  shop_name: '',
  shop_logo: '',
  shop_banner: '',
  shop_theme: 'default',
  shop_notice: '',
  contact_info: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 12,
  total: 0
})

const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjY2NjIiBmb250LXNpemU9IjE0Ij7llJrml6Dlm77niYg8L3RleHQ+PC9zdmc+'

async function fetchShop() {
  try {
    const res = await axios.get('/api/shop/' + merchantNo.value)
    if (res.data.code === 0) {
      Object.assign(shopInfo, res.data.data.shop || {})
      products.value = res.data.data.products || []
      const catSet = new Set()
      products.value.forEach(p => {
        if (p.category) catSet.add(p.category)
      })
      categories.value = Array.from(catSet)
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchProducts() {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: keyword.value,
      category: category.value
    }
    const res = await axios.get('/api/shop/' + merchantNo.value + '/products', { params })
    if (res.data.code === 0) {
      products.value = res.data.data.list
      pagination.total = res.data.data.total
    }
  } finally {
    loading.value = false
  }
}

function searchProducts() {
  pagination.page = 1
  fetchProducts()
}

function goDetail(product) {
  router.push(`/shop/${merchantNo.value}/product/${product.id}`)
}

function goQuery() {
  router.push(`/shop/${merchantNo.value}/query`)
}

onMounted(() => {
  fetchShop()
  fetchProducts()
})
</script>

<style scoped>
.shop-home {
  min-height: 100vh;
  background: #f5f5f5;
  padding-bottom: 60px;
}

.shop-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 40px 20px;
  text-align: center;
}

.shop-info {
  max-width: 1200px;
  margin: 0 auto;
}

.shop-name {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 8px;
}

.shop-notice {
  font-size: 14px;
  opacity: 0.9;
}

.shop-content {
  max-width: 1200px;
  margin: -20px auto 0;
  padding: 0 20px;
}

.search-bar {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  background: white;
  padding: 16px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.search-bar .el-input {
  flex: 1;
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}

.product-card {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.product-image {
  position: relative;
  width: 100%;
  height: 180px;
  overflow: hidden;
  background: #f5f5f5;
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-stock {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(245, 108, 108, 0.9);
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.product-info {
  padding: 12px;
}

.product-name {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-category {
  font-size: 12px;
  color: #909399;
  margin-bottom: 8px;
}

.product-price {
  color: #f56c6c;
}

.price-symbol {
  font-size: 12px;
}

.price-value {
  font-size: 20px;
  font-weight: 600;
}

.pagination {
  margin-top: 24px;
  display: flex;
  justify-content: center;
}

.shop-footer {
  margin-top: 40px;
  text-align: center;
  color: #909399;
  font-size: 13px;
}

.footer-links {
  margin-bottom: 8px;
}

.footer-contact {
  margin-top: 8px;
}
</style>
