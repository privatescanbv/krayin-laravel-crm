using System;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Playwright;
using Reqnroll;
using UiTests.Support;

namespace UiTests.Steps
{
    [Binding]
    public class PartnerProductSteps
    {
        private readonly BrowserDriver _driver;
        private string _createdProductName = "";

        public PartnerProductSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I open the partner products page")]
        public async Task GivenIOpenThePartnerProductsPage()
        {
            var url = $"{TestConfig.BaseUrl}/admin/settings/partner-products";
            await _driver.Page.GotoAsync(url);
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products$"));
        }

        [When("I click on create partner product")]
        public async Task WhenIClickOnCreatePartnerProduct()
        {
            await _driver.Page.GetByRole(AriaRole.Link, new() { Name = "Partnerproduct toevoegen" }).ClickAsync();
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products/create$"));
        }

        [When(@"I fill in the partner product form with name ""(.*)"" and price ""(.*)""")]
        public async Task WhenIFillInThePartnerProductForm(string name, string price)
        {
            _createdProductName = $"{name} {Guid.NewGuid():N}";
            
            // Fill in required fields
            await _driver.Page.FillAsync("input[name='name']", _createdProductName);
            await _driver.Page.FillAsync("input[name='sales_price']", price);
            await _driver.Page.FillAsync("input[name='partner_name']", $"Test Partner {Guid.NewGuid():N}");
            
            // Select currency (should default to EUR)
            // Select resource type - select the first option that's not empty
            var resourceTypeSelect = _driver.Page.Locator("select[name='resource_type_id']");
            await resourceTypeSelect.SelectOptionAsync(new[] { "1" });
            
            // Select at least one clinic
            var clinicSelect = _driver.Page.Locator("select[name='clinics[]']");
            var firstOption = await clinicSelect.Locator("option").First.GetAttributeAsync("value");
            if (!string.IsNullOrEmpty(firstOption))
            {
                await clinicSelect.SelectOptionAsync(new[] { firstOption });
            }
            
            // Check active checkbox
            await _driver.Page.CheckAsync("input[name='active'][value='1']");
        }

        [When("I save the partner product")]
        public async Task WhenISaveThePartnerProduct()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { Name = "Opslaan" }).ClickAsync();
        }

        [Then("I should be redirected to the partner products overview")]
        public async Task ThenIShouldBeRedirectedToThePartnerProductsOverview()
        {
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(
                new Regex(".*/admin/settings/partner-products$"), 
                new() { Timeout = 10000 }
            );
        }

        [When("I edit the first partner product")]
        public async Task WhenIEditTheFirstPartnerProduct()
        {
            // Wait for the datagrid to load
            await _driver.Page.WaitForSelectorAsync("table tbody tr", new() { Timeout = 5000 });
            
            // Find the row with our created product and click edit
            var row = _driver.Page.Locator($"table tbody tr:has-text('{_createdProductName}')").First;
            await row.Locator("a[title='Bewerken']").ClickAsync();
            
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products/edit/\\d+$"));
        }

        [When(@"I change the price to ""(.*)""")]
        public async Task WhenIChangeThePriceTo(string newPrice)
        {
            var priceInput = _driver.Page.Locator("input[name='sales_price']");
            await priceInput.FillAsync("");
            await priceInput.FillAsync(newPrice);
        }

        [Then("I should see the updated price in the overview")]
        public async Task ThenIShouldSeeTheUpdatedPriceInTheOverview()
        {
            // Wait for redirect and page load
            await _driver.Page.WaitForLoadStateAsync(LoadState.NetworkIdle);
            
            // Verify the success message appears
            await Assertions.Expect(_driver.Page.Locator("text=succesvol bijgewerkt")).ToBeVisibleAsync(new() { Timeout = 5000 });
            
            // Verify the updated price is visible in the table
            var row = _driver.Page.Locator($"table tbody tr:has-text('{_createdProductName}')").First;
            await Assertions.Expect(row.Locator("text=175,50")).ToBeVisibleAsync();
        }
    }
}