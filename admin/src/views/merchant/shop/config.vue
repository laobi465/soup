<template>
  <div class="shop-config">
    <div class="page-header">
      <h2>店铺配置</h2>
    </div>

    <el-card class="config-card">
      <template #header>
        <span>店铺信息</span>
      </template>

      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="店铺名称" prop="shop_name">
          <el-input v-model="form.shop_name" placeholder="请输入店铺名称" />
        </el-form-item>

        <el-form-item label="店铺Logo">
          <el-input v-model="form.shop_logo" placeholder="请输入Logo图片URL" />
        </el-form-item>

        <el-form-item label="店铺Banner">
          <el-input v-model="form.shop_banner" placeholder="请输入Banner图片URL" />
        </el-form-item>

        <el-form-item label="店铺主题">
          <el-select v-model="form.shop_theme" style="width: 200px">
            <el-option label="默认主题" value="default" />
            <el-option label="清新蓝" value="blue" />
            <el-option label="活力橙" value="orange" />
            <el-option label="暗夜黑" value="dark" />
          </el-select>
        </el-form-item>

        <el-form-item label="店铺公告">
          <el-input v-model="form.shop_notice" type="textarea" :rows="3" placeholder="请输入店铺公告" />
        </el-form-item>

        <el-form-item label="联系方式">
          <el-input v-model="form.contact_info" type="textarea" :rows="2" placeholder="请输入联系方式" />
        </el-form-item>

        <el-form-item label="店铺状态">
          <el-switch v-model="form.shop_status" :active-value="1" :inactive-value="0" />
          <span style="margin-left: 10px; color: #909399; font-size: 13px;">
            {{ form.shop_status === 1 ? '正常营业' : '暂停营业' }}
          </span>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="submitting">保存配置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="info-card" style="margin-top: 20px;">
      <template #header>
        <span>店铺链接</span>
      </template>
      <div class="shop-link">
        <el-input :value="shopUrl" readonly>
          <template #append>
            <el-button @click="copyLink">复制</el-button>
          </template>
        </el-input>
        <div class="shop-link-tip">
          您的店铺编号：<strong>{{ merchantNo }}</strong>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getProfile } from '@/api/merchant/profile'

const submitting = ref(false)
const formRef = ref(null)
const merchantNo = ref('')

const form = reactive({
  shop_name: '',
  shop_logo: '',
  shop_banner: '',
  shop_theme: 'default',
  shop_notice: '',
  contact_info: '',
  shop_status: 1
})

const rules = {
  shop_name: [{ required: true, message: '请输入店铺名称', trigger: 'blur' }]
}

const shopUrl = computed(() => {
  return window.location.origin + '/#/shop/' + merchantNo.value
})

async function fetchProfile() {
  try {
    const res = await getProfile()
    if (res.code === 0 && res.data && res.data.merchant) {
      const m = res.data.merchant
      merchantNo.value = m.merchant_no || ''
      form.shop_name = m.merchant_name || ''
    }
  } catch (e) {
    console.error(e)
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      ElMessage.success('保存成功')
    } finally {
      submitting.value = false
    }
  })
}

function copyLink() {
  navigator.clipboard.writeText(shopUrl.value).then(() => {
    ElMessage.success('复制成功')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

onMounted(() => {
  fetchProfile()
})
</script>

<style scoped>
.shop-config {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.config-card {
  max-width: 700px;
}

.shop-link {
  max-width: 500px;
}

.shop-link-tip {
  margin-top: 12px;
  color: #606266;
  font-size: 14px;
}

.info-card {
  max-width: 700px;
}
</style>
