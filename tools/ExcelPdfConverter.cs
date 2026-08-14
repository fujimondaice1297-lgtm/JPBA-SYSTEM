using System;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;

internal static class ExcelPdfConverter
{
    private const int XlTypePdf = 0;
    private const int XlQualityStandard = 0;

    public static int Main(string[] args)
    {
        try
        {
            Console.OutputEncoding = new UTF8Encoding(false);
        }
        catch
        {
            // Output encoding is best-effort only.
        }

        if (args.Length != 2)
        {
            Console.Error.WriteLine("Usage: convert_excel_to_pdf.exe input.xlsx output.pdf");
            return 2;
        }

        string inputPath = Path.GetFullPath(args[0]);
        string outputPath = Path.GetFullPath(args[1]);

        if (!File.Exists(inputPath))
        {
            Console.Error.WriteLine("Input workbook was not found: " + inputPath);
            return 3;
        }

        object excel = null;
        object workbooks = null;
        object workbook = null;

        try
        {
            Type excelType = Type.GetTypeFromProgID("Excel.Application");
            if (excelType == null)
            {
                Console.Error.WriteLine("Microsoft Excel is not installed or is not registered.");
                return 10;
            }

            excel = Activator.CreateInstance(excelType);
            dynamic excelApp = excel;
            excelApp.Visible = false;
            excelApp.DisplayAlerts = false;
            excelApp.AskToUpdateLinks = false;
            excelApp.EnableEvents = false;

            try
            {
                // msoAutomationSecurityForceDisable
                excelApp.AutomationSecurity = 3;
            }
            catch
            {
                // Older Excel versions may not expose this property.
            }

            workbooks = excelApp.Workbooks;
            dynamic workbookCollection = workbooks;
            workbook = workbookCollection.Open(inputPath, 0, true);
            dynamic openedWorkbook = workbook;
            openedWorkbook.ExportAsFixedFormat(
                XlTypePdf,
                outputPath,
                XlQualityStandard,
                true,
                false
            );

            if (!File.Exists(outputPath))
            {
                Console.Error.WriteLine("Microsoft Excel completed without creating the PDF.");
                return 21;
            }

            Console.WriteLine(outputPath);
            return 0;
        }
        catch (COMException exception)
        {
            Console.Error.WriteLine(
                "Excel COM conversion failed. HRESULT=0x"
                + exception.ErrorCode.ToString("X8")
                + " "
                + exception.Message
            );
            return 20;
        }
        catch (Exception exception)
        {
            Console.Error.WriteLine(
                "Excel conversion failed. "
                + exception.GetType().FullName
                + ": "
                + exception.Message
            );
            return 20;
        }
        finally
        {
            if (workbook != null)
            {
                try
                {
                    ((dynamic)workbook).Close(false);
                }
                catch
                {
                    // Continue cleanup even when closing fails.
                }
            }

            if (excel != null)
            {
                try
                {
                    ((dynamic)excel).Quit();
                }
                catch
                {
                    // Continue cleanup even when quitting fails.
                }
            }

            ReleaseComObject(workbook);
            ReleaseComObject(workbooks);
            ReleaseComObject(excel);

            GC.Collect();
            GC.WaitForPendingFinalizers();
            GC.Collect();
            GC.WaitForPendingFinalizers();
        }
    }

    private static void ReleaseComObject(object value)
    {
        if (value == null || !Marshal.IsComObject(value))
        {
            return;
        }

        try
        {
            Marshal.FinalReleaseComObject(value);
        }
        catch
        {
            // Process exit is the final isolation boundary.
        }
    }
}
