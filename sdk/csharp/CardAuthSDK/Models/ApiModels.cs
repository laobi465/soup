using System;
using System.Collections.Generic;

namespace CardAuthSDK.Models
{
    public class ApiResponse<T>
    {
        public int Code { get; set; }
        public string Message { get; set; }
        public T Data { get; set; }
        public long Timestamp { get; set; }

        public bool IsSuccess
        {
            get { return Code == 0 || Code == 200; }
        }
    }

    public class CardVerifyResult
    {
        public int CardId { get; set; }
        public int CardType { get; set; }
        public string CardTypeText { get; set; }
        public int Status { get; set; }
        public string StatusText { get; set; }
        public string ExpireTime { get; set; }
        public int RemainingDuration { get; set; }
        public int BindDeviceCount { get; set; }
        public int BindLimit { get; set; }
        public bool IsPermanent { get; set; }
        public bool IsSoftExpired { get; set; }
    }

    public class CardQueryResult
    {
        public int CardType { get; set; }
        public string CardTypeText { get; set; }
        public int Status { get; set; }
        public string StatusText { get; set; }
        public string ExpireTime { get; set; }
        public int RemainingDuration { get; set; }
        public int BindDeviceCount { get; set; }
        public int BindLimit { get; set; }
        public bool IsPermanent { get; set; }
        public string ActivateTime { get; set; }
    }

    public class OnlineCountResult
    {
        public int OnlineCount { get; set; }
        public int AppId { get; set; }
    }

    public class AnnouncementResult
    {
        public string Title { get; set; }
        public string Content { get; set; }
        public bool Enabled { get; set; }
        public List<string> Variables { get; set; }
    }
}
