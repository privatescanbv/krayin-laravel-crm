using System;
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

        public async Task StartAsync()
        {
            _playwright = await Playwright.CreateAsync();
            _browser = await _playwright.Chromium.LaunchAsync(new BrowserTypeLaunchOptions
            {
                Headless = true   // zet op false voor visuele debugging
            });

            _context = await _browser.NewContextAsync();
            Page = await _context.NewPageAsync();
        }

        public async ValueTask DisposeAsync()
        {
            if (_browser != null)
                await _browser.CloseAsync();

            _playwright?.Dispose();
        }
    }
}
