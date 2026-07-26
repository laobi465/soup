Imports System
Imports System.Threading.Tasks
Imports CardAuthSDK
Imports CardAuthSDK.Models

Module Program
    Sub Main(args As String())
        MainAsync(args).GetAwaiter().GetResult()
    End Sub

    Async Function MainAsync(args As String()) As Task
        Dim appKey As String = "your_app_key"
        Dim appSecret As String = "your_app_secret"
        Dim baseUrl As String = "http://localhost"

        Dim client As New CardAuthClient(appKey, appSecret, baseUrl)

        Console.WriteLine("=== 卡密验证 SDK 示例 ===")
        Console.WriteLine()

        Console.WriteLine("1. 卡密验证")
        Dim verifyResult As ApiResponse(Of CardVerifyResult) = Await client.VerifyAsync("CARD-NO-001", "device-fingerprint-001", "My PC")
        If verifyResult.IsSuccess Then
            Console.WriteLine("  验证成功!")
            Console.WriteLine("  卡密类型: " & verifyResult.Data.CardTypeText)
            Console.WriteLine("  状态: " & verifyResult.Data.StatusText)
            Console.WriteLine("  到期时间: " & verifyResult.Data.ExpireTime)
            Console.WriteLine("  剩余时长: " & verifyResult.Data.RemainingDuration & " 秒")
            Console.WriteLine("  绑定设备数: " & verifyResult.Data.BindDeviceCount & "/" & verifyResult.Data.BindLimit)
        Else
            Console.WriteLine("  验证失败: " & verifyResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("2. 卡密激活")
        Dim activateResult As ApiResponse(Of CardVerifyResult) = Await client.ActivateAsync("CARD-NO-001", "device-fingerprint-001", "My PC")
        If activateResult.IsSuccess Then
            Console.WriteLine("  激活成功!")
        Else
            Console.WriteLine("  激活失败: " & activateResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("3. 卡密查询")
        Dim queryResult As ApiResponse(Of CardQueryResult) = Await client.QueryAsync("CARD-NO-001")
        If queryResult.IsSuccess Then
            Console.WriteLine("  查询成功!")
            Console.WriteLine("  卡密类型: " & queryResult.Data.CardTypeText)
            Console.WriteLine("  状态: " & queryResult.Data.StatusText)
            Console.WriteLine("  到期时间: " & queryResult.Data.ExpireTime)
            Console.WriteLine("  激活时间: " & queryResult.Data.ActivateTime)
        Else
            Console.WriteLine("  查询失败: " & queryResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("4. 心跳上报")
        Dim heartbeatResult As ApiResponse(Of Object) = Await client.HeartbeatAsync("CARD-NO-001", "device-fingerprint-001")
        If heartbeatResult.IsSuccess Then
            Console.WriteLine("  心跳上报成功!")
        Else
            Console.WriteLine("  心跳上报失败: " & heartbeatResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("5. 在线人数")
        Dim onlineResult As ApiResponse(Of OnlineCountResult) = Await client.OnlineCountAsync()
        If onlineResult.IsSuccess Then
            Console.WriteLine("  当前在线人数: " & onlineResult.Data.OnlineCount)
        Else
            Console.WriteLine("  获取在线人数失败: " & onlineResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("6. 系统公告")
        Dim announcementResult As ApiResponse(Of AnnouncementResult) = Await client.AnnouncementAsync()
        If announcementResult.IsSuccess Then
            Console.WriteLine("  公告标题: " & announcementResult.Data.Title)
            Console.WriteLine("  公告内容: " & announcementResult.Data.Content)
        Else
            Console.WriteLine("  获取公告失败: " & announcementResult.Message)
        End If
        Console.WriteLine()

        Console.WriteLine("=== 示例执行完毕 ===")
        Console.ReadLine()
    End Function
End Module
