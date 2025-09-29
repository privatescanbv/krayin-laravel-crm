using Reqnroll;
using System;   // <-- nodig voor Exception

[Binding]
public class Hooks
{
    [BeforeTestRun(Order = 0)]
    public static void EnsurePlaywrightInstalled()
    {
        // Roept de Playwright installer aan en geeft een exitcode terug
        var exitCode = Microsoft.Playwright.Program.Main(new[] { "install", "--with-deps" });

        if (exitCode != 0)
        {
            throw new Exception($"Playwright install failed with exit code {exitCode}");
        }
    }
}
