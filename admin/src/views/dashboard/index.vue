<template>
  <div class="dashboard">
    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #409eff">
              <el-icon :size="32"><user /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">1,234</div>
              <div class="stat-label">用户总数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #67c23a">
              <el-icon :size="32"><key /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">5,678</div>
              <div class="stat-label">卡密总数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #e6a23c">
              <el-icon :size="32"><view /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">12,345</div>
              <div class="stat-label">验证次数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #f56c6c">
              <el-icon :size="32"><wallet /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">¥98,765</div>
              <div class="stat-label">收入总额</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card header="最近活动">
          <el-table :data="activityList" style="width: 100%">
            <el-table-column prop="time" label="时间" width="180" />
            <el-table-column prop="user" label="用户" width="120" />
            <el-table-column prop="action" label="操作" />
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card header="系统信息">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="系统版本">v1.0.0</el-descriptions-item>
            <el-descriptions-item label="运行环境">Docker</el-descriptions-item>
            <el-descriptions-item label="PHP版本">8.2</el-descriptions-item>
            <el-descriptions-item label="数据库">MySQL 8.0</el-descriptions-item>
            <el-descriptions-item label="缓存">Redis 7</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
const activityList = [
  { time: '2024-01-15 10:30:00', user: 'admin', action: '创建了新的卡密批次' },
  { time: '2024-01-15 09:20:00', user: 'user001', action: '使用卡密激活成功' },
  { time: '2024-01-15 08:15:00', user: 'admin', action: '更新了系统配置' },
  { time: '2024-01-14 18:45:00', user: 'user002', action: '注册新账户' },
  { time: '2024-01-14 16:30:00', user: 'admin', action: '添加了新的软件产品' }
]
</script>

<style scoped>
.stat-card {
  border-radius: 8px;
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #333;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: #999;
}
</style>
