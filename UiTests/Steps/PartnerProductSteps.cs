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
            _createdProductName = $"{name} {DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";

            // Wait for the form to be loaded
            await _driver.Page.WaitForSelectorAsync("input[name='name']", new() { Timeout = 5000 });

            // Fill in required fields with explicit waits
            await _driver.Page.Locator("input[name='name']").FillAsync(_createdProductName);
            await _driver.Page.Locator("input[name='sales_price']").FillAsync(price);

            // Select resource type - wait for it to be visible first
            var resourceTypeSelect = _driver.Page.Locator("select[name='resource_type_id']");
            await resourceTypeSelect.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 5000 });

            var resourceTypeOptions = await resourceTypeSelect.Locator("option:not([value=''])").AllAsync();
            if (resourceTypeOptions.Count > 0)
            {
                var firstResourceTypeValue = await resourceTypeOptions[0].GetAttributeAsync("value");
                if (!string.IsNullOrEmpty(firstResourceTypeValue))
                {
                    await resourceTypeSelect.SelectOptionAsync(new[] { firstResourceTypeValue });
                }
            }

            // Select at least one clinic - fail early if none available
            var clinicSelect = _driver.Page.Locator("select[name='clinics[]']");
            await clinicSelect.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 5000 });

            var clinicOptions = await clinicSelect.Locator("option[value]").AllAsync();
            if (clinicOptions.Count == 0)
            {
                throw new Exception("No clinic options available to select. Seed test data with at least one clinic.");
            }

            var firstClinicValue = await clinicOptions[0].GetAttributeAsync("value");
            if (string.IsNullOrEmpty(firstClinicValue))
            {
                throw new Exception("First clinic option has empty value. Seed test data properly.");
            }

            await clinicSelect.SelectOptionAsync(new[] { firstClinicValue });

            // Check active checkbox
            var activeCheckbox = _driver.Page.Locator("input[name='active'][value='1']");
            await activeCheckbox.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 5000 });
            await activeCheckbox.CheckAsync();
        }

        [When("I save the partner product")]
        public async Task WhenISaveThePartnerProduct()
        {
            // Log current form state before saving
            var clinicsSelected = await _driver.Page.Locator("select[name='clinics[]']").EvaluateAsync<string[]>("(el) => Array.from(el.selectedOptions).map(o => o.value)");
            var resourcesSelected = await _driver.Page.Locator("select[name='resources[]']").EvaluateAsync<string[]>("(el) => Array.from(el.selectedOptions).map(o => o.value)");
            Console.WriteLine($"[DEBUG] Clinics selected: [{string.Join(", ", clinicsSelected)}]");
            Console.WriteLine($"[DEBUG] Resources selected: [{string.Join(", ", resourcesSelected)}]");

            await _driver.Page.GetByRole(AriaRole.Button, new() { NameRegex = new Regex("^(Opslaan|Save)$") }).ClickAsync();

            // Wait for either redirect to index or show validation errors
            var indexUrlRegex = new Regex(".*/admin/settings/partner-products$");
            var createUrlRegex = new Regex(".*/admin/settings/partner-products/create$");

            // Small race: give the app time to navigate or render validation
            await _driver.Page.WaitForLoadStateAsync(LoadState.NetworkIdle);

            // If there are validation errors, surface them immediately
            var errors = _driver.Page.Locator(".control-error, .text-red-600, .alert-error");
            var errorCount = await errors.CountAsync();
            if (errorCount > 0)
            {
                string allErrors = string.Empty;
                for (var i = 0; i < errorCount; i++)
                {
                    var text = await errors.Nth(i).InnerTextAsync();
                    allErrors += $"\n- {text}";
                }

                await CaptureDiagnosticsAsync("partner-product-save-validation-errors");
                throw new Exception($"Validation errors after save:{allErrors}");
            }
            // Do not assert redirect here; the Then-step validates URL.
        }

        [Then("I should be redirected to the partner products overview")]
        public async Task ThenIShouldBeRedirectedToThePartnerProductsOverview()
        {
            var indexRegex = new Regex(".*/admin/settings/partner-products$");
            var createRegex = new Regex(".*/admin/settings/partner-products/create$");

            try
            {
                await Assertions.Expect(_driver.Page).ToHaveURLAsync(indexRegex, new() { Timeout = 10000 });
            }
            catch
            {
                if (createRegex.IsMatch(_driver.Page.Url))
                {
                    // Gather feedback: current URL, visible validation errors, and body excerpt
                    var currentUrl = _driver.Page.Url;
                    var errors = _driver.Page.Locator(".control-error, .text-red-600, .alert-error");
                    var errorCount = await errors.CountAsync();

                    string allErrors = errorCount > 0 ? "" : "(geen zichtbare validatiefouten gevonden)";
                    for (var i = 0; i < errorCount; i++)
                    {
                        var text = await errors.Nth(i).InnerTextAsync();
                        allErrors += $"\n- {text}";
                    }

                    string bodyExcerpt = string.Empty;
                    try
                    {
                        var bodyText = await _driver.Page.EvaluateAsync<string>("() => document.body.innerText");
                        bodyExcerpt = bodyText?.Length > 800 ? bodyText.Substring(0, 800) + "..." : bodyText ?? string.Empty;
                    }
                    catch { /* ignore */ }

                    await CaptureDiagnosticsAsync("partner-product-still-on-create-after-save");

                    throw new Exception($"Niet doorgestuurd naar overzicht.\nURL: {currentUrl}\nZichtbare fouten:{allErrors}\nBody excerpt:\n{bodyExcerpt}");
                }

                throw;
            }
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

        [When(@"I change the name to ""(.*)""")]
        public async Task WhenIChangeTheNameTo(string newName)
        {
            var uniqueName = $"{newName} {DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";

            var nameInput = _driver.Page.Locator("input[name='name']");
            await nameInput.FillAsync("");
            await nameInput.FillAsync(uniqueName);
            await nameInput.BlurAsync();
            _createdProductName = uniqueName;
        }

        [Then("I should see the updated name in the overview")]
        public async Task ThenIShouldSeeTheUpdatedNameInTheOverview()
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

            // Verify the updated name is visible in the table
            var row = _driver.Page
                .Locator(".table-responsive .row.max-lg\\:hidden")
                .Filter(new() { HasTextString = _createdProductName })
                .First;

            try
            {
                await Assertions.Expect(row).ToContainTextAsync(_createdProductName, new() { Timeout = 10000 });
            }
            catch
            {
                await CaptureDiagnosticsAsync("name-update-failed");
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
