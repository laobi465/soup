Imports System
Imports System.Collections.Generic
Imports System.Security.Cryptography
Imports System.Text

Namespace CardAuthSDK.Utils
    Public Module SignHelper
        Public Function GenerateNonce() As String
            Return Guid.NewGuid().ToString("N").Substring(0, 16)
        End Function

        Public Function GetTimestamp() As String
            Return (CInt(DateTime.UtcNow.Subtract(New DateTime(1970, 1, 1)).TotalSeconds)).ToString()
        End Function

        Public Function HmacSha256(data As String, key As String) As String
            Using hmac As New HMACSHA256(Encoding.UTF8.GetBytes(key))
                Dim hashBytes As Byte() = hmac.ComputeHash(Encoding.UTF8.GetBytes(data))
                Dim sb As New StringBuilder()
                For i As Integer = 0 To hashBytes.Length - 1
                    sb.Append(hashBytes(i).ToString("x2"))
                Next
                Return sb.ToString()
            End Using
        End Function

        Public Function Sign(method As String, path As String, timestamp As String, nonce As String, body As String, appSecret As String) As String
            Dim signString As String = method.ToUpper() + path + timestamp + nonce + body
            Return HmacSha256(signString, appSecret)
        End Function
    End Module
End Namespace
