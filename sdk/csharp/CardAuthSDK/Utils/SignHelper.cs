using System;
using System.Collections.Generic;
using System.Security.Cryptography;
using System.Text;

namespace CardAuthSDK.Utils
{
    public static class SignHelper
    {
        public static string GenerateNonce()
        {
            return Guid.NewGuid().ToString("N").Substring(0, 16);
        }

        public static string GetTimestamp()
        {
            return ((int)(DateTime.UtcNow.Subtract(new DateTime(1970, 1, 1))).TotalSeconds).ToString();
        }

        public static string HmacSha256(string data, string key)
        {
            using (var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(key)))
            {
                byte[] hashBytes = hmac.ComputeHash(Encoding.UTF8.GetBytes(data));
                StringBuilder sb = new StringBuilder();
                for (int i = 0; i < hashBytes.Length; i++)
                {
                    sb.Append(hashBytes[i].ToString("x2"));
                }
                return sb.ToString();
            }
        }

        public static string Sign(string method, string path, string timestamp, string nonce, string body, string appSecret)
        {
            string signString = method.ToUpper() + path + timestamp + nonce + body;
            return HmacSha256(signString, appSecret);
        }
    }
}
