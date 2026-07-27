import request from '@/utils/request'

// 创建注入任务（获取上传URL）
export function createInjectTask(data) {
  return request({
    url: '/merchant/apk-inject/create',
    method: 'post',
    data
  })
}

// 确认上传完成并投递队列
export function dispatchInjectTask(taskId) {
  return request({
    url: '/merchant/apk-inject/dispatch',
    method: 'post',
    data: { task_id: taskId }
  })
}

// 任务列表
export function getInjectTaskList(params) {
  return request({
    url: '/merchant/apk-inject/list',
    method: 'get',
    params
  })
}

// 任务详情
export function getInjectTaskDetail(id) {
  return request({
    url: `/merchant/apk-inject/detail/${id}`,
    method: 'get'
  })
}

// 获取下载URL
export function getInjectDownloadUrl(id) {
  return request({
    url: `/merchant/apk-inject/download/${id}`,
    method: 'get'
  })
}
