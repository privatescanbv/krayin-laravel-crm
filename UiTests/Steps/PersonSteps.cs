using System;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Playwright;
using Reqnroll;
using UiTests.Support;

namespace UiTests.Steps
{
    [Binding]
    public class PersonSteps
    {
        private readonly BrowserDriver _driver;

        public PersonSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I ensure two test persons exist")]
        public async Task GivenIEnsureTwoTestPersonsExist()
        {
            // Create two persons via UI to ensure data exists in CI
            await CreatePersonIfMissing("CI Test", "PersonOne", $"ci-one-{Guid.NewGuid():N}@example.com");
            await CreatePersonIfMissing("CI Test", "PersonTwo", $"ci-two-{Guid.NewGuid():N}@example.com");
        }

        private async Task CreatePersonIfMissing(string firstName, string lastName, string email)
        {
            // Navigate to person create page
            var url = $"{TestConfig.BaseUrl}/admin/contacts/persons/create";
            await _driver.Page.GotoAsync(url);
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/contacts/persons/create$"));

            // Fill required name fields
            await _driver.Page.FillAsync("input[name=first_name]", firstName);
            await _driver.Page.FillAsync("input[name=last_name]", lastName);

            // Fill first email input
            var emailInput = _driver.Page.Locator("input[name^=emails][name$='[value]']").First;
            if (await emailInput.CountAsync() > 0)
            {
                await emailInput.FillAsync(email);
            }

            // Submit (Save)
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Opslaan" }).ClickAsync();

            // Allow some time for server redirect to index; be tolerant in CI
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/contacts/persons(?:[/?].*)?$"), new() { Timeout = 30000 });
        }
    }
}
