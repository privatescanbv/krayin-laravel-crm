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
            await _driver.Page.GotoAsync(url);

            var user = Environment.GetEnvironmentVariable("TEST_USERNAME") ?? "mark.bulthuis@privatescan.nl";
            var pass = Environment.GetEnvironmentVariable("TEST_PASSWORD") ?? "8AAZ5jc%e&AF";

            await _driver.Page.FillAsync("#email", user);
            await _driver.Page.FillAsync("#password", pass);
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Inloggen" }).ClickAsync();

            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/dashboard$"));
        }
    }
}

