using System;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Playwright;
using Reqnroll;
using UiTests.Support;

namespace UiTests.Steps
{
    [Binding]
    public class LeadCreationSteps
    {
        private readonly BrowserDriver _driver;

        public LeadCreationSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I open the lead create page")]
        public async Task GivenIOpenTheLeadCreatePage()
        {
            var url = $"{TestConfig.BaseUrl}/admin/leads/create";
            await _driver.Page.GotoAsync(url);
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/leads/create$"));
        }

        [When("I go to step 2 without selecting a person")]
        public async Task WhenIGoToStep2WithoutSelectingAPerson()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Verder naar stap 2" }).ClickAsync();
            await Assertions.Expect(_driver.Page.GetByText("Stap 2: Lead gegevens")).ToBeVisibleAsync();
        }

        [When(@"I select the first person suggestion for query ""(.*)""")]
        public async Task WhenISelectFirstPersonForQuery(string query)
        {
            // The first person lookup input inside step 1
            var input = _driver.Page.GetByPlaceholder("Zoek persoon...");
            await input.FillAsync(query);
            // Wait dropdown and click first option
            var firstOption = _driver.Page.Locator(".lookup__results .lookup-result, .lookup-result").First;
            await firstOption.WaitForAsync(new() { State = WaitForSelectorState.Visible });
            await firstOption.ClickAsync();
        }

        [When("I go to step 2")]
        public async Task WhenIGoToStep2()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Verder naar stap 2" }).ClickAsync();
            await Assertions.Expect(_driver.Page.GetByText("Stap 2: Lead gegevens")).ToBeVisibleAsync();
        }

        [When("I fill the required lead fields")]
        public async Task WhenIFillTheRequiredLeadFields()
        {
            // The form has required radios for metals, claustrophobia, allergies
            await _driver.Page.CheckAsync("input[name=metals][value='0']");
            await _driver.Page.CheckAsync("input[name=claustrophobia][value='0']");
            await _driver.Page.CheckAsync("input[name=allergies][value='0']");

            // Minimal: ensure a description is set to avoid empty issues
            await _driver.Page.FillAsync("textarea[name=description]", "Automated test lead");
        }

        [When("I save the lead")]
        public async Task WhenISaveTheLead()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Opslaan" }).ClickAsync();
        }

        [Then("I should be redirected to the lead view page")]
        public async Task ThenIShouldBeRedirectedToLeadView()
        {
            // Expect url like /admin/leads/view/{id}
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/leads/view/\\d+$"));
        }
    }
}

