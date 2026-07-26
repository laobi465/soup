Imports System
Imports System.Collections.Generic
Imports System.Net.Http
Imports System.Text
Imports System.Threading.Tasks
Imports CardAuthSDK.Models
Imports CardAuthSDK.Utils
Imports Newtonsoft.Json

Namespace CardAuthSDK
    Public Class CardAuthClient
        Private ReadOnly _appKey As String
        Private ReadOnly _appSecret As String
        Private ReadOnly _baseUrl As String
        Private ReadOnly _httpClient As HttpClient
        Private Const TimeoutSeconds As Integer = 15

        Public Sub New(appKey As String, appSecret As String, Optional baseUrl As String = "http://localhost")
            _appKey = appKey
            _appSecret = appSecret
            _baseUrl = baseUrl.TrimEnd("/"c)
            _httpClient = New HttpClient()
            _httpClient.Timeout = TimeSpan.FromSeconds(TimeoutSeconds)
        End Sub

        Private Async Function SendRequestAsync(Of T)(method As HttpMethod, path As String, Optional data As Object = Nothing) As Task(Of ApiResponse(Of T))
            Try
                Dim timestamp As String = SignHelper.GetTimestamp()
                Dim nonce As String = SignHelper.GenerateNonce()
                Dim body As String = If(data IsNot Nothing, JsonConvert.SerializeObject(data, Formatting.None), "")
                Dim sign As String = SignHelper.Sign(method.Method, path, timestamp, nonce, body, _appSecret)

                Dim request As New HttpRequestMessage(method, _baseUrl + path)
                request.Headers.Add("X-AppKey", _appKey)
                request.Headers.Add("X-Timestamp", timestamp)
                request.Headers.Add("X-Nonce", nonce)
                request.Headers.Add("X-Sign", sign)

                If data IsNot Nothing AndAlso method IsNot HttpMethod.Get Then
                    request.Content = New StringContent(body, Encoding.UTF8, "application/json")
                End If

                Dim response As HttpResponseMessage = Await _httpClient.SendAsync(request)
                Dim responseContent As String = Await response.Content.ReadAsStringAsync()

                Return JsonConvert.DeserializeObject(Of ApiResponse(Of T))(responseContent)
            Catch ex As Exception
                Return New ApiResponse(Of T) With {
                    .Code = -1,
                    .Message = "请求失败: " & ex.Message,
                    .Data = Nothing,
                    .Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
                }
            End Try
        End Function

        Public Async Function VerifyAsync(cardNo As String, Optional deviceFingerprint As String = "", Optional deviceName As String = "") As Task(Of ApiResponse(Of CardVerifyResult))
            Dim data = New With {
                Key .card_no = cardNo,
                Key .device_fingerprint = deviceFingerprint,
                Key .device_name = deviceName
            }
            Return Await SendRequestAsync(Of CardVerifyResult)(HttpMethod.Post, "/api/v1/card/verify", data)
        End Function

        Public Async Function ActivateAsync(cardNo As String, deviceFingerprint As String, Optional deviceName As String = "") As Task(Of ApiResponse(Of CardVerifyResult))
            Dim data = New With {
                Key .card_no = cardNo,
                Key .device_fingerprint = deviceFingerprint,
                Key .device_name = deviceName
            }
            Return Await SendRequestAsync(Of CardVerifyResult)(HttpMethod.Post, "/api/v1/card/activate", data)
        End Function

        Public Async Function RebindAsync(cardNo As String, oldDevice As String, newDevice As String, Optional deviceName As String = "") As Task(Of ApiResponse(Of Object))
            Dim data = New With {
                Key .card_no = cardNo,
                Key .old_device = oldDevice,
                Key .new_device = newDevice,
                Key .device_name = deviceName
            }
            Return Await SendRequestAsync(Of Object)(HttpMethod.Post, "/api/v1/card/rebind", data)
        End Function

        Public Async Function QueryAsync(cardNo As String) As Task(Of ApiResponse(Of CardQueryResult))
            Dim data = New With {
                Key .card_no = cardNo
            }
            Return Await SendRequestAsync(Of CardQueryResult)(HttpMethod.Post, "/api/v1/card/query", data)
        End Function

        Public Async Function HeartbeatAsync(cardNo As String, deviceFingerprint As String) As Task(Of ApiResponse(Of Object))
            Dim data = New With {
                Key .card_no = cardNo,
                Key .device_fingerprint = deviceFingerprint
            }
            Return Await SendRequestAsync(Of Object)(HttpMethod.Post, "/api/v1/card/heartbeat", data)
        End Function

        Public Async Function OnlineCountAsync() As Task(Of ApiResponse(Of OnlineCountResult))
            Return Await SendRequestAsync(Of OnlineCountResult)(HttpMethod.Get, "/api/v1/card/online-count")
        End Function

        Public Async Function AnnouncementAsync() As Task(Of ApiResponse(Of AnnouncementResult))
            Return Await SendRequestAsync(Of AnnouncementResult)(HttpMethod.Get, "/api/v1/app/announcement")
        End Function
    End Class
End Namespace
