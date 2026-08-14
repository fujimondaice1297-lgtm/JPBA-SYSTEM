Option Explicit

Dim inputPath
Dim outputPath
Dim excel
Dim workbook
Dim exitCode
Dim errorMessage

If WScript.Arguments.Count <> 2 Then
    WScript.StdErr.WriteLine "Usage: convert_excel_to_pdf.vbs input.xlsx output.pdf"
    WScript.Quit 2
End If

inputPath = WScript.Arguments(0)
outputPath = WScript.Arguments(1)
Set excel = Nothing
Set workbook = Nothing
exitCode = 0
errorMessage = ""

On Error Resume Next

Set excel = CreateObject("Excel.Application")
If Err.Number <> 0 Then
    exitCode = 10
    errorMessage = "Excel.Application could not be started. HRESULT=" & Hex(Err.Number) & " " & Err.Description
    Err.Clear
Else
    excel.Visible = False
    excel.DisplayAlerts = False
    excel.AskToUpdateLinks = False

    Set workbook = excel.Workbooks.Open(inputPath, 0, True)
    If Err.Number <> 0 Then
        exitCode = 11
        errorMessage = "Workbook could not be opened. HRESULT=" & Hex(Err.Number) & " " & Err.Description
        Err.Clear
    Else
        workbook.ExportAsFixedFormat 0, outputPath, 0, True, False
        If Err.Number <> 0 Then
            exitCode = 12
            errorMessage = "PDF export failed. HRESULT=" & Hex(Err.Number) & " " & Err.Description
            Err.Clear
        End If
    End If
End If

If Not workbook Is Nothing Then
    workbook.Close False
    Set workbook = Nothing
End If

If Not excel Is Nothing Then
    excel.Quit
    Set excel = Nothing
End If

On Error GoTo 0

If exitCode <> 0 Then
    WScript.StdErr.WriteLine errorMessage
    WScript.Quit exitCode
End If

WScript.StdOut.WriteLine outputPath
WScript.Quit 0
