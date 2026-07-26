import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/store/user'
import router from '@/router'

const service = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  },
  retry: 2,
  retryDelay: 1000
})

const pendingRequests = new Map()
let isRefreshing = false
let failedQueue = []

function generateRequestKey(config) {
  const { method, url, params, data } = config
  return [method, url, JSON.stringify(params), JSON.stringify(data)].join('&')
}

function addPendingRequest(config) {
  const requestKey = generateRequestKey(config)
  if (pendingRequests.has(requestKey)) {
    const controller = pendingRequests.get(requestKey)
    controller.abort()
    pendingRequests.delete(requestKey)
  }
  const controller = new AbortController()
  config.signal = controller.signal
  pendingRequests.set(requestKey, controller)
}

function removePendingRequest(config) {
  const requestKey = generateRequestKey(config)
  if (pendingRequests.has(requestKey)) {
    pendingRequests.delete(requestKey)
  }
}

function processQueue(error, token = null) {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

function isNetworkError(error) {
  return !error.response && error.message && (
    error.message.includes('Network Error') ||
    error.message.includes('timeout') ||
    error.message.includes('abort')
  )
}

service.interceptors.request.use(
  (config) => {
    const userStore = useUserStore()
    
    removePendingRequest(config)
    if (!config.noCancel) {
      addPendingRequest(config)
    }

    if (userStore.token) {
      config.headers['Authorization'] = 'Bearer ' + userStore.token
    }
    
    if (config.noRetry === undefined) {
      config.noRetry = false
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

service.interceptors.response.use(
  (response) => {
    const userStore = useUserStore()
    const res = response.data

    removePendingRequest(response.config)

    if (res.code === 0 || res.code === 200) {
      return res
    }

    if (res.code === 401) {
      const originalRequest = response.config

      if (userStore.refreshToken && !originalRequest._retry) {
        if (isRefreshing) {
          return new Promise((resolve, reject) => {
            failedQueue.push({ resolve, reject })
          })
            .then(token => {
              originalRequest.headers['Authorization'] = 'Bearer ' + token
              return service(originalRequest)
            })
            .catch(err => {
              return Promise.reject(err)
            })
        }

        originalRequest._retry = true
        isRefreshing = true

        return new Promise((resolve, reject) => {
          userStore.refreshTokenFn()
            .then(res => {
              if (res.code === 0) {
                const token = res.data.access_token
                originalRequest.headers['Authorization'] = 'Bearer ' + token
                processQueue(null, token)
                resolve(service(originalRequest))
              } else {
                processQueue(new Error(res.message || '刷新令牌失败'), null)
                reject(new Error(res.message || '刷新令牌失败'))
              }
            })
            .catch(err => {
              processQueue(err, null)
              reject(err)
            })
            .finally(() => {
              isRefreshing = false
            })
        })
      }

      ElMessageBox.confirm('登录状态已过期，请重新登录', '提示', {
        confirmButtonText: '重新登录',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        userStore.clearUserState()
        router.push('/login')
      })

      return Promise.reject(new Error(res.message || '未授权'))
    }

    if (res.code === 403) {
      ElMessage({
        message: res.message || '无权限访问',
        type: 'error',
        duration: 3000
      })
      return Promise.reject(new Error(res.message || '无权限访问'))
    }

    ElMessage({
      message: res.message || '请求失败',
      type: 'error',
      duration: 3000
    })

    return Promise.reject(new Error(res.message || '请求失败'))
  },
  (error) => {
    console.error('请求错误:', error)
    const userStore = useUserStore()
    const config = error.config

    if (config) {
      removePendingRequest(config)
    }

    if (error.code === 'ERR_CANCELED' || error.name === 'CanceledError') {
      return Promise.reject(error)
    }

    if (config && !config.noRetry && isNetworkError(error)) {
      config.__retryCount = config.__retryCount || 0
      const retryTimes = service.defaults.retry || 2
      const retryDelay = service.defaults.retryDelay || 1000

      if (config.__retryCount < retryTimes) {
        config.__retryCount++
        return new Promise(resolve => {
          setTimeout(() => {
            resolve(service(config))
          }, retryDelay)
        })
      }
    }

    if (error.response) {
      const status = error.response.status
      if (status === 401) {
        ElMessageBox.confirm('登录状态已过期，请重新登录', '提示', {
          confirmButtonText: '重新登录',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          userStore.clearUserState()
          router.push('/login')
        })
      } else if (status === 403) {
        ElMessage({
          message: '无权限访问',
          type: 'error',
          duration: 3000
        })
      } else if (status === 429) {
        const data = error.response.data
        ElMessage({
          message: data?.message || '请求过于频繁，请稍后再试',
          type: 'warning',
          duration: 3000
        })
      } else {
        ElMessage({
          message: error.response.data?.message || error.message || '网络错误',
          type: 'error',
          duration: 3000
        })
      }
    } else {
      ElMessage({
        message: error.message || '网络错误',
        type: 'error',
        duration: 3000
      })
    }

    return Promise.reject(error)
  }
)

export function cancelAllRequests() {
  pendingRequests.forEach(controller => {
    controller.abort()
  })
  pendingRequests.clear()
}

export default service
