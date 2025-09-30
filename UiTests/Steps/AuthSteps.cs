using System;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Playwright;
using Reqnroll;
using UiTests.Support;

namespace UiTests.Steps
{
    [Binding]
    public class AuthSteps
    {
        private readonly BrowserDriver _driver;

        public AuthSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I am logged in")]
        public async Task GivenIAmLoggedIn()
        {
            await _driver.StartAsync();

            var url = $"{TestConfig.BaseUrl}/admin/login";
            await _driver.Page.GotoAsync(url, new() { WaitUntil = WaitUntilState.NetworkIdle });

            var user = Environment.GetEnvironmentVariable("TEST_USERNAME") ?? "mark.bulthuis@privatescan.nl";
            var pass = Environment.GetEnvironmentVariable("TEST_PASSWORD") ?? "8AAZ5jc%e&AF";

            // Wait for fields to be visible in CI
            await _driver.Page.Locator("#email").WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 30000 });
            await _driver.Page.Locator("#password").WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 30000 });

            await _driver.Page.FillAsync("#email", user);
            await _driver.Page.FillAsync("#password", pass);
            await _driver.Page.GetByRole(AriaRole.Button, new() { NameRegex = new Regex("^(Inloggen|Login)$") }).ClickAsync();

            try
            {
                await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/dashboard$"), new() { Timeout = 30000 });
            }
            catch
            {
                await CaptureAuthDiagnosticsAsync();
                throw;
            }
        }

        private async Task CaptureAuthDiagnosticsAsync()
        {
            try
            {
                var ts = DateTime.UtcNow.ToString("yyyyMMdd_HHmmss");
                await _driver.Page.ScreenshotAsync(new() { Path = $"artifacts/login_failed_{ts}.png", FullPage = true });
                Console.WriteLine($"[AuthDiag] URL: {_driver.Page.Url}");
                var body = await _driver.Page.EvaluateAsync<string>("() => document.body.innerText");
                Console.WriteLine($"[AuthDiag] Body: {body?.Substring(0, Math.Min(2000, body.Length))}");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[AuthDiag] Failed to capture: {ex.Message}");
            }
        }
    }
}

