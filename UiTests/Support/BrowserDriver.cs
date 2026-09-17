using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using Microsoft.Playwright;

namespace UiTests.Support
{
    public class BrowserDriver : IAsyncDisposable
    {
        private IPlaywright _playwright;
        private IBrowser _browser;
        private IBrowserContext _context;

        public IPage Page { get; private set; }

        // Collected browser console errors (e.g. Vue template compile failures).
        // Checked in Hooks.AfterScenario so any tested page that logs a JS error
        // fails the build, without needing a bespoke assertion per scenario.
        public List<string> ConsoleErrors { get; } = new();

        public async Task StartAsync()
        {
            _playwright = await Playwright.CreateAsync();
            _browser = await _playwright.Chromium.LaunchAsync(new BrowserTypeLaunchOptions
            {
                Headless = true   // zet op false voor visuele debugging
            });

            _context = await _browser.NewContextAsync(new BrowserNewContextOptions
            {
                Locale = "nl-NL",
                ExtraHTTPHeaders = new System.Collections.Generic.Dictionary<string, string>
                {
                    { "Accept-Language", "nl-NL,nl;q=0.9" }
                }
            });

            // Set generous defaults for CI flakiness
            _context.SetDefaultTimeout(30000);

            Page = await _context.NewPageAsync();
            Page.SetDefaultTimeout(30000);

            Page.Console += (_, msg) =>
            {
                if (msg.Type == "error")
                {
                    ConsoleErrors.Add($"[{Page.Url}] {msg.Text}");
                }
            };

            Page.PageError += (_, error) =>
            {
                ConsoleErrors.Add($"[{Page.Url}] {error}");
            };
        }

        public async ValueTask DisposeAsync()
        {
            if (_browser != null)
                await _browser.CloseAsync();

            _playwright?.Dispose();
        }
    }
}
