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
            // Use the multi-contact matcher search input
            var input = _driver.Page.GetByPlaceholder("Zoek op naam, e-mail, telefoon...");
            await input.FillAsync(query);

            // Wait for the suggestions list to render and click the first suggestion
            var firstSuggestion = _driver.Page.Locator("ul li").First;
            await firstSuggestion.WaitForAsync(new() { State = WaitForSelectorState.Visible });
            await firstSuggestion.ClickAsync();
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

            // Required personal fields when no person selected in step 1
            // Ensure first and last name have values
            var firstName = _driver.Page.Locator("input[name=first_name]");
            var lastName = _driver.Page.Locator("input[name=last_name]");
            if (await firstName.CountAsync() > 0)
            {
                await firstName.FillAsync("Test");
            }
            if (await lastName.CountAsync() > 0)
            {
                await lastName.FillAsync("Lead");
            }

            // At least one contact: fill first email input if present
            var emailValueInput = _driver.Page.Locator("input[name^=emails][name$='[value]']").First;
            if (await emailValueInput.CountAsync() > 0)
            {
                await emailValueInput.FillAsync($"test+{Guid.NewGuid():N}@example.com");
            }
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
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/leads/view/\\d+$"), new() { Timeout = 30000 });
        }
    }
}

