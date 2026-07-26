using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;
using CardAuthSDK.Models;
using CardAuthSDK.Utils;
using Newtonsoft.Json;

namespace CardAuthSDK
{
    public class CardAuthClient
    {
        private readonly string _appKey;
        private readonly string _appSecret;
        private readonly string _baseUrl;
        private readonly HttpClient _httpClient;
        private const int TimeoutSeconds = 15;

        public CardAuthClient(string appKey, string appSecret, string baseUrl = "http://localhost")
        {
            _appKey = appKey;
            _appSecret = appSecret;
            _baseUrl = baseUrl.TrimEnd('/');
            _httpClient = new HttpClient
            {
                Timeout = TimeSpan.FromSeconds(TimeoutSeconds)
            };
        }

        private async Task<ApiResponse<T>> SendRequestAsync<T>(HttpMethod method, string path, object data = null)
        {
            try
            {
                string timestamp = SignHelper.GetTimestamp();
                string nonce = SignHelper.GenerateNonce();
                string body = data != null ? JsonConvert.SerializeObject(data, Formatting.None) : "";
                string sign = SignHelper.Sign(method.Method, path, timestamp, nonce, body, _appSecret);

                var request = new HttpRequestMessage(method, _baseUrl + path);
                request.Headers.Add("X-AppKey", _appKey);
                request.Headers.Add("X-Timestamp", timestamp);
                request.Headers.Add("X-Nonce", nonce);
                request.Headers.Add("X-Sign", sign);

                if (data != null && method != HttpMethod.Get)
                {
                    request.Content = new StringContent(body, Encoding.UTF8, "application/json");
                }

                var response = await _httpClient.SendAsync(request);
                var responseContent = await response.Content.ReadAsStringAsync();
                
                return JsonConvert.DeserializeObject<ApiResponse<T>>(responseContent);
            }
            catch (Exception ex)
            {
                return new ApiResponse<T>
                {
                    Code = -1,
                    Message = $"请求失败: {ex.Message}",
                    Data = default(T),
                    Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
                };
            }
        }

        public async Task<ApiResponse<CardVerifyResult>> VerifyAsync(string cardNo, string deviceFingerprint = "", string deviceName = "")
        {
            var data = new
            {
                card_no = cardNo,
                device_fingerprint = deviceFingerprint,
                device_name = deviceName
            };
            return await SendRequestAsync<CardVerifyResult>(HttpMethod.Post, "/api/v1/card/verify", data);
        }

        public async Task<ApiResponse<CardVerifyResult>> ActivateAsync(string cardNo, string deviceFingerprint, string deviceName = "")
        {
            var data = new
            {
                card_no = cardNo,
                device_fingerprint = deviceFingerprint,
                device_name = deviceName
            };
            return await SendRequestAsync<CardVerifyResult>(HttpMethod.Post, "/api/v1/card/activate", data);
        }

        public async Task<ApiResponse<object>> RebindAsync(string cardNo, string oldDevice, string newDevice, string deviceName = "")
        {
            var data = new
            {
                card_no = cardNo,
                old_device = oldDevice,
                new_device = newDevice,
                device_name = deviceName
            };
            return await SendRequestAsync<object>(HttpMethod.Post, "/api/v1/card/rebind", data);
        }

        public async Task<ApiResponse<CardQueryResult>> QueryAsync(string cardNo)
        {
            var data = new
            {
                card_no = cardNo
            };
            return await SendRequestAsync<CardQueryResult>(HttpMethod.Post, "/api/v1/card/query", data);
        }

        public async Task<ApiResponse<object>> HeartbeatAsync(string cardNo, string deviceFingerprint)
        {
            var data = new
            {
                card_no = cardNo,
                device_fingerprint = deviceFingerprint
            };
            return await SendRequestAsync<object>(HttpMethod.Post, "/api/v1/card/heartbeat", data);
        }

        public async Task<ApiResponse<OnlineCountResult>> OnlineCountAsync()
        {
            return await SendRequestAsync<OnlineCountResult>(HttpMethod.Get, "/api/v1/card/online-count");
        }

        public async Task<ApiResponse<AnnouncementResult>> AnnouncementAsync()
        {
            return await SendRequestAsync<AnnouncementResult>(HttpMethod.Get, "/api/v1/app/announcement");
        }
    }
}
