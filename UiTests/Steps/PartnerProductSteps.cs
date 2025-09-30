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
        private string _newPrice = "";

        public PartnerProductSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I open the partner products page")]
        public async Task GivenIOpenThePartnerProductsPage()
        {
            var url = $"{TestConfig.BaseUrl}/admin/settings/partner-products";
            await _driver.Page.GotoAsync(url, new() { WaitUntil = WaitUntilState.NetworkIdle });
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products$"));

            // Wait for the page content to be visible
            await _driver.Page.WaitForSelectorAsync(".flex.flex-col.gap-4", new() { Timeout = 5000 });
        }

        [When("I click on create partner product")]
        public async Task WhenIClickOnCreatePartnerProduct()
        {
            // Wait for the button to be visible and clickable
            var createButton = _driver.Page.Locator("a:has-text('Partnerproduct toevoegen'), a:has-text('Add Partner Product')");
            await createButton.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
            await createButton.ClickAsync();

            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products/create$"));
        }

        [When(@"I fill in the partner product form with name ""(.*)"" and price ""(.*)""")]
        public async Task WhenIFillInThePartnerProductForm(string name, string price)
        {
            _createdProductName = $"{name} {Guid.NewGuid():N}";

            // Wait for the form to be loaded
            await _driver.Page.WaitForSelectorAsync("input[name='name']", new() { Timeout = 5000 });

            // Fill in required fields
            await _driver.Page.FillAsync("input[name='name']", _createdProductName);
            await _driver.Page.FillAsync("input[name='sales_price']", price);
            await _driver.Page.FillAsync("input[name='partner_name']", $"Test Partner {Guid.NewGuid():N}");

            // Select currency (should default to EUR)

            // Select resource type - select the first non-empty option
            var resourceTypeSelect = _driver.Page.Locator("select[name='resource_type_id']");
            var resourceTypeOptions = await resourceTypeSelect.Locator("option[value]:not([value=''])").AllAsync();
            if (resourceTypeOptions.Count > 0)
            {
                var firstResourceTypeValue = await resourceTypeOptions[0].GetAttributeAsync("value");
                if (!string.IsNullOrEmpty(firstResourceTypeValue))
                {
                    await resourceTypeSelect.SelectOptionAsync(new[] { firstResourceTypeValue });
                }
            }

            // Select at least one clinic - select the first available option
            var clinicSelect = _driver.Page.Locator("select[name='clinics[]']");
            var clinicOptions = await clinicSelect.Locator("option").AllAsync();
            if (clinicOptions.Count > 0)
            {
                var firstClinicValue = await clinicOptions[0].GetAttributeAsync("value");
                if (!string.IsNullOrEmpty(firstClinicValue))
                {
                    await clinicSelect.SelectOptionAsync(new[] { firstClinicValue });
                    
                    // Trigger change event manually for the JavaScript to pick up the selection
                    await clinicSelect.EvaluateAsync("(element) => element.dispatchEvent(new Event('change', { bubbles: true }))");
                    
                    // Wait for resources to be loaded (resources field becomes enabled and populated)
                    var resourcesSelect = _driver.Page.Locator("select[name='resources[]']");
                    
                    // Wait until the select is no longer disabled (max 5 seconds)
                    try
                    {
                        await _driver.Page.WaitForFunctionAsync(
                            "() => !document.querySelector('select[name=\"resources[]\"]')?.disabled",
                            timeout: 5000
                        );
                    }
                    catch
                    {
                        // If resources don't load, continue anyway (clinic might have no resources)
                    }
                    
                    // Extra wait to ensure options are populated
                    await _driver.Page.WaitForTimeoutAsync(500);
                }
            }

            // Check active checkbox
            await _driver.Page.CheckAsync("input[name='active'][value='1']");
        }

        [When("I save the partner product")]
        public async Task WhenISaveThePartnerProduct()
        {
            await _driver.Page.GetByRole(AriaRole.Button, new() { NameRegex = new Regex("^(Opslaan|Save)$") }).ClickAsync();

            // Wait for a likely success indicator or network idle to ensure save completed
            try
            {
                // Dutch and English success toasts
                var successToast = _driver.Page.Locator(
                    "text=Partnerproduct succesvol bijgewerkt."
                );
                await successToast.First.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 5000 });
            }
            catch
            {
                // Fallback: wait for network idle
                await _driver.Page.WaitForLoadStateAsync(LoadState.NetworkIdle);
            }
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
            await _driver.Page.WaitForSelectorAsync(".table-responsive .row:has(.icon-edit)", new() { Timeout = 10000 });

            // Find the row with our created product and click the edit action icon
            var row = _driver.Page
                .Locator(".table-responsive .row.max-lg\\:hidden")
                .Filter(new() { HasTextString = _createdProductName })
                .First;

            await row.Locator(".icon-edit").ClickAsync();

            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/settings/partner-products/edit/\\d+$"));
        }

        [When(@"I change the price to ""(.*)""")]
        public async Task WhenIChangeThePriceTo(string newPrice)
        {
            _newPrice = newPrice;
            var priceInput = _driver.Page.Locator("input[name='sales_price']");
            await priceInput.FillAsync("");
            await priceInput.FillAsync(newPrice);
            await priceInput.BlurAsync();
        }

        [Then("I should see the updated price in the overview")]
        public async Task ThenIShouldSeeTheUpdatedPriceInTheOverview()
        {
            // Ensure we are on the overview and data is fresh
            var indexUrl = $"{TestConfig.BaseUrl}/admin/settings/partner-products";
            if (!_driver.Page.Url.EndsWith("/admin/settings/partner-products"))
            {
                await _driver.Page.GotoAsync(indexUrl, new() { WaitUntil = WaitUntilState.NetworkIdle });
            }
            else
            {
                await _driver.Page.ReloadAsync(new() { WaitUntil = WaitUntilState.NetworkIdle });
            }

            // Verify the updated price is visible in the table
            var row = _driver.Page
                .Locator(".table-responsive .row.max-lg\\:hidden")
                .Filter(new() { HasTextString = _createdProductName })
                .First;

            // In datagrid order: ID, Partnernaam, Naam, Valuta, Verkoopprijs, Actief, Acties
            var priceCell = row.Locator("p.break-words").Nth(4);

            var entered = _newPrice;
            var dotVariant = _newPrice.Replace(',', '.');
            var pricePattern = new Regex($"^(?:{Regex.Escape(entered)}|{Regex.Escape(dotVariant)})$");

            try
            {
                await Assertions.Expect(priceCell).ToHaveTextAsync(pricePattern, new() { Timeout = 10000 });
            }
            catch
            {
                await CaptureDiagnosticsAsync("price-update-failed");
                throw;
            }
        }

        private async Task CaptureDiagnosticsAsync(string tag)
        {
            try
            {
                var timestamp = DateTime.UtcNow.ToString("yyyyMMdd_HHmmss");
                var prefix = $"artifacts/{tag}_{timestamp}";

                // Ensure folder exists is handled by CI workspace (may ignore if not supported)
                await _driver.Page.ScreenshotAsync(new() { Path = $"{prefix}.png", FullPage = true });

                var url = _driver.Page.Url;
                var bodyText = await _driver.Page.EvaluateAsync<string>("() => document.body.innerText");

                Console.WriteLine($"[Diagnostics] URL: {url}");
                Console.WriteLine($"[Diagnostics] Body excerpt: {bodyText?.Substring(0, Math.Min(2000, bodyText.Length))}");

                // Log visible validation errors if any
                var errors = _driver.Page.Locator(".control-error, .text-red-600, .alert-error");
                var count = await errors.CountAsync();
                for (var i = 0; i < count; i++)
                {
                    var text = await errors.Nth(i).InnerTextAsync();
                    Console.WriteLine($"[Diagnostics] Validation/Error: {text}");
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[Diagnostics] Failed to capture diagnostics: {ex.Message}");
            }
        }
    }
}
