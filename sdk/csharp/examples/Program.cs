using System;
using System.Threading.Tasks;
using CardAuthSDK;
using CardAuthSDK.Models;

namespace CardAuthSDK.Examples
{
    class Program
    {
        static async Task Main(string[] args)
        {
            string appKey = "your_app_key";
            string appSecret = "your_app_secret";
            string baseUrl = "http://localhost";

            var client = new CardAuthClient(appKey, appSecret, baseUrl);

            string cardNo = "TEST-CARD-001";
            string deviceFingerprint = "device-abc-123";
            string deviceName = "My PC";

            Console.WriteLine("=== 卡密查询 ===");
            var queryResult = await client.QueryAsync(cardNo);
            PrintResult(queryResult);
            Console.WriteLine();

            Console.WriteLine("=== 卡密激活 ===");
            var activateResult = await client.ActivateAsync(cardNo, deviceFingerprint, deviceName);
            PrintResult(activateResult);
            Console.WriteLine();

            Console.WriteLine("=== 卡密验证 ===");
            var verifyResult = await client.VerifyAsync(cardNo, deviceFingerprint, deviceName);
            PrintResult(verifyResult);
            Console.WriteLine();

            Console.WriteLine("=== 心跳 ===");
            var heartbeatResult = await client.HeartbeatAsync(cardNo, deviceFingerprint);
            PrintResult(heartbeatResult);
            Console.WriteLine();

            Console.WriteLine("=== 在线人数 ===");
            var onlineResult = await client.OnlineCountAsync();
            PrintResult(onlineResult);
            Console.WriteLine();

            Console.WriteLine("=== 系统公告 ===");
            var announcementResult = await client.AnnouncementAsync();
            PrintResult(announcementResult);
        }

        static void PrintResult<T>(ApiResponse<T> result)
        {
            Console.WriteLine($"Code: {result.Code}");
            Console.WriteLine($"Message: {result.Message}");
            if (result.Data != null)
            {
                Console.WriteLine($"Data: {Newtonsoft.Json.JsonConvert.SerializeObject(result.Data, Newtonsoft.Json.Formatting.Indented)}");
            }
        }
    }
}
