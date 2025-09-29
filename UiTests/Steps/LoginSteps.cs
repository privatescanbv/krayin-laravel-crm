using System;
using System.Text.RegularExpressions;
using Reqnroll;
using System.Threading.Tasks;
using Microsoft.Playwright;
using UiTests.Support;   // voor BrowserDriver en TestConfig

namespace UiTests.Steps
{
    [Binding]
    public class LoginSteps
    {
        private readonly BrowserDriver _driver;

        public LoginSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I open the login page")]
        public async Task GivenIOpenTheLoginPage()
        {
            await _driver.StartAsync();

            var url = $"{TestConfig.BaseUrl}/admin/login";
            await _driver.Page.GotoAsync(url);
        }

        [When(@"I enter username ""(.*)"" and password ""(.*)""")]
        public async Task WhenIEnterCredentials(string user, string pass)
        {
            await _driver.Page.FillAsync("#email", user);
            await _driver.Page.FillAsync("#password", pass);
        }

        [When("I click the login button")]
        public async Task WhenIClickTheLoginButton()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Inloggen" }).ClickAsync();
        }

        [Then("I should see the dashboard")]
        public async Task ThenIShouldSeeTheDashboard()
        {
            // Even de URL loggen om te zien waar we belanden
            Console.WriteLine("Current URL: " + _driver.Page.Url);

            // Wacht specifiek op de <p> met tekst 'Dashboard'
//            await _driver.Page.WaitForSelectorAsync("p:has-text(\"Dashboard\")", new() { Timeout = 30000 });
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/dashboard$"));
//            await _driver.Page.Locator("p:has-text(\"Dashboard\")").First.WaitForAsync();

            // Debug info
            Console.WriteLine("URL: " + _driver.Page.Url);
        }

    }
}
