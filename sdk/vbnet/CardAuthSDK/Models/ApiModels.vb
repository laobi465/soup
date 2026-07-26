Imports System
Imports System.Collections.Generic

Namespace CardAuthSDK.Models
    Public Class ApiResponse(Of T)
        Public Property Code As Integer
        Public Property Message As String
        Public Property Data As T
        Public Property Timestamp As Long

        Public ReadOnly Property IsSuccess As Boolean
            Get
                Return Code = 0 OrElse Code = 200
            End Get
        End Property
    End Class

    Public Class CardVerifyResult
        Public Property CardId As Integer
        Public Property CardType As Integer
        Public Property CardTypeText As String
        Public Property Status As Integer
        Public Property StatusText As String
        Public Property ExpireTime As String
        Public Property RemainingDuration As Integer
        Public Property BindDeviceCount As Integer
        Public Property BindLimit As Integer
        Public Property IsPermanent As Boolean
        Public Property IsSoftExpired As Boolean
    End Class

    Public Class CardQueryResult
        Public Property CardType As Integer
        Public Property CardTypeText As String
        Public Property Status As Integer
        Public Property StatusText As String
        Public Property ExpireTime As String
        Public Property RemainingDuration As Integer
        Public Property BindDeviceCount As Integer
        Public Property BindLimit As Integer
        Public Property IsPermanent As Boolean
        Public Property ActivateTime As String
    End Class

    Public Class OnlineCountResult
        Public Property OnlineCount As Integer
        Public Property AppId As Integer
    End Class

    Public Class AnnouncementResult
        Public Property Title As String
        Public Property Content As String
        Public Property Enabled As Boolean
        Public Property Variables As List(Of String)
    End Class
End Namespace
