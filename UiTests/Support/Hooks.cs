using Reqnroll;
using System;   // <-- nodig voor Exception
using System.Linq;
using UiTests.Support;

[Binding]
public class Hooks
{
    private readonly BrowserDriver _driver;

    public Hooks(BrowserDriver driver)
    {
        _driver = driver;
    }

    [BeforeTestRun(Order = 0)]
    public static void EnsurePlaywrightInstalled()
    {
        // Roept de Playwright installer aan en geeft een exitcode terug
        var exitCode = Microsoft.Playwright.Program.Main(new[] { "install" });

        if (exitCode != 0)
        {
            throw new Exception($"Playwright install failed with exit code {exitCode}");
        }
    }

    // Fails the scenario if any page it visited logged a browser console error
    // (e.g. a Vue template compile failure like the duplicate-v-bind bug).
    // Catches this class of bug on every page an existing scenario navigates to,
    // without a bespoke assertion per test.
    [AfterScenario]
    public void AssertNoConsoleErrors()
    {
        if (_driver.ConsoleErrors.Count == 0)
        {
            return;
        }

        throw new Exception(
            "Browser console errors were logged during this scenario:\n"
            + string.Join("\n", _driver.ConsoleErrors.Distinct()));
    }
}
