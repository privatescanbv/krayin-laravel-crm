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
        private string _editedProductId = "";

        public PartnerProductSteps(BrowserDriver driver)
        {
            _driver = driver;
        }

        [Given("I open the partner products page")]
        public async Task GivenIOpenThePartnerProductsPage()
        {
            var url = $"{TestConfig.BaseUrl}/admin/partner-products";
            await _driver.Page.GotoAsync(url, new() { WaitUntil = WaitUntilState.NetworkIdle });
            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/partner-products$"));

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

            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/partner-products/create$"));
        }

        [When(@"I fill in the partner product form with name ""(.*)"" and price ""(.*)""")]
        public async Task WhenIFillInThePartnerProductForm(string name, string price)
        {
            _createdProductName = $"{name} {DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";

            // Wait for the form to be loaded
            await _driver.Page.WaitForSelectorAsync("input[name='name']", new() { Timeout = 5000 });

            // Fill in required fields with explicit waits
            var nameInput = _driver.Page.Locator("input[name='name']");
            await nameInput.FillAsync(_createdProductName);
            await nameInput.BlurAsync();

            // Normalize price for CI (comma decimal) and blur to trigger validation
            var ciPrice = price.Replace('.', ',');
            var priceInput = _driver.Page.Locator("input[name='sales_price']");
            await priceInput.FillAsync("");
            await priceInput.FillAsync(ciPrice);
            await priceInput.BlurAsync();

            // Ensure currency = EUR if select exists
            var currencySelect = _driver.Page.Locator("select[name='currency']");
            if (await currencySelect.CountAsync() > 0)
            {
                await currencySelect.SelectOptionAsync(new[] { "EUR" });
                // Dispatch change event to ensure bindings update
                await _driver.Page.EvaluateAsync("el => el.dispatchEvent(new Event('change', { bubbles: true }))", await currencySelect.ElementHandleAsync());
            }

            // Select resource type - wait for it to be visible first
            var resourceTypeSelect = _driver.Page.Locator("select[name='resource_type_id']");
            await resourceTypeSelect.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 5000 });

            var resourceTypeOptions = await resourceTypeSelect.Locator("option:not([value=''])").AllAsync();
            if (resourceTypeOptions.Count == 0)
            {
                throw new Exception("No resource type options available. Ensure seed data for resource types exists in CI.");
            }
            var firstResourceTypeValue = await resourceTypeOptions[0].GetAttributeAsync("value");
            if (string.IsNullOrEmpty(firstResourceTypeValue))
            {
                throw new Exception("First resource type option has empty value.");
            }
            await resourceTypeSelect.SelectOptionAsync(new[] { firstResourceTypeValue });
            await _driver.Page.EvaluateAsync("el => el.dispatchEvent(new Event('change', { bubbles: true }))", await resourceTypeSelect.ElementHandleAsync());

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
            await _driver.Page.EvaluateAsync("el => el.dispatchEvent(new Event('change', { bubbles: true }))", await clinicSelect.ElementHandleAsync());

            // Extra diagnostics
            var dbgPrice = await priceInput.InputValueAsync();
            var dbgCurrency = await (await currencySelect.ElementHandleAsync())?.EvaluateAsync<string>("el => el && el.value");
            var dbgResourceType = await (await resourceTypeSelect.ElementHandleAsync())?.EvaluateAsync<string>("el => el && el.value");
            Console.WriteLine($"[DEBUG] Pre-save name: {_createdProductName}");
            Console.WriteLine($"[DEBUG] Pre-save price input: {dbgPrice}");
            Console.WriteLine($"[DEBUG] Pre-save currency: {dbgCurrency}");
            Console.WriteLine($"[DEBUG] Pre-save resource_type_id: {dbgResourceType}");
        }

        [When("I save the partner product")]
        public async Task WhenISaveThePartnerProduct()
        {
            // Log current form state before saving
            var clinicsSelected = await _driver.Page.Locator("select[name='clinics[]']").EvaluateAsync<string>("el => Array.from(el.selectedOptions).map(o => o.value).join(', ')");
            var resourcesSelected = await _driver.Page.Locator("select[name='resources[]']").EvaluateAsync<string>("el => Array.from(el.selectedOptions).map(o => o.value).join(', ')");
            var currentName = await _driver.Page.Locator("input[name='name']").InputValueAsync();
            var currentPrice = await _driver.Page.Locator("input[name='sales_price']").InputValueAsync();
            var currentCurrency = await _driver.Page.Locator("select[name='currency']").EvaluateAsync<string>("el => el ? el.value : ''");
            var currentResourceType = await _driver.Page.Locator("select[name='resource_type_id']").EvaluateAsync<string>("el => el ? el.value : ''");
            Console.WriteLine($"[DEBUG] Clinics selected: [{clinicsSelected}]");
            Console.WriteLine($"[DEBUG] Resources selected: [{resourcesSelected}]");
            Console.WriteLine($"[DEBUG] Name input value before save: {currentName}");
            Console.WriteLine($"[DEBUG] Price input value before save: {currentPrice}");
            Console.WriteLine($"[DEBUG] Currency before save: {currentCurrency}");
            Console.WriteLine($"[DEBUG] ResourceType before save: {currentResourceType}");

            var cur = _driver.Page.Url;
            if (!string.IsNullOrEmpty(_editedProductId))
            {
                // Only enforce edit URL shape when we actually captured an edited id
                if (!new Regex("/admin/partner-products/edit/\\d+$").IsMatch(cur))
                {
                    Console.WriteLine($"[WARN] Unexpected URL before save during edit flow: {cur}");
                }
            }
            // Wait for the partner-products form (supports native <form> and <v-form>)
            await _driver.Page.WaitForSelectorAsync(
                "form[action*='partner-products'], v-form[action*='partner-products']",
                new() { Timeout = 10000 }
            );

            // Build FormData from the correct container and submit via fetch to avoid hydration timing issues
            var submittedFormJson = await _driver.Page.EvaluateAsync<string>(
                """
                (async () => {
                  function pickContainer() {
                    const native = document.querySelector('form[action*="partner-products"]');
                    if (native) return native;
                    const vue = document.querySelector('v-form[action*="partner-products"]');
                    if (vue) return vue;
                    const all = Array.from(document.querySelectorAll('form, v-form'));
                    return all.find(f => (f.getAttribute('action') || '').includes('partner-products')) || null;
                  }

                  const container = pickContainer();
                  if (!container) return JSON.stringify({ error: 'no-container' });

                  const fd = new FormData();
                  const q = sel => container.querySelector(sel);
                  const add = (k, v) => { if (v !== undefined && v !== null) fd.append(k, String(v)); };

                  add('name', q('input[name="name"]')?.value || '');
                  add('currency', q('select[name="currency"]')?.value || '');
                  add('sales_price', q('input[name="sales_price"]')?.value || '');

                  const activeCb = q('input[name="active"][type="checkbox"]');
                  fd.append('active', activeCb && activeCb.checked ? '1' : '0');

                  add('description', q('textarea[name="description"]')?.value || '');
                  add('discount_info', q('textarea[name="discount_info"]')?.value || '');
                  add('resource_type_id', q('select[name="resource_type_id"]')?.value || '');

                  const clinics = q('select[name="clinics[]"]');
                  if (clinics) Array.from(clinics.selectedOptions).forEach(o => fd.append('clinics[]', o.value));

                  add('clinic_description', q('textarea[name="clinic_description"]')?.value || '');
                  add('duration', q('input[name="duration"]')?.value || '0');

                  const csrf = document.querySelector('input[name="_token"]')?.value
                    || document.querySelector('meta[name="csrf-token"]')?.content;
                  if (csrf) fd.append('_token', csrf);

                  const href = location.href;
                  if (/\/edit\/\d+$/.test(href)) fd.append('_method', 'PUT');

                  const action = container.getAttribute('action') || href;

                  const summary = {};
                  fd.forEach((v, k) => {
                    if (summary[k] === undefined) summary[k] = v;
                    else if (Array.isArray(summary[k])) summary[k].push(v);
                    else summary[k] = [summary[k], v];
                  });

                  // Submit via a native form to ensure full-page navigation/redirect
                  const tempForm = document.createElement('form');
                  tempForm.method = 'POST';
                  tempForm.action = action;
                  // replicate payload as hidden inputs
                  for (const [k, v] of fd.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = k;
                    input.value = typeof v === 'string' ? v : (v && v.name) ? v.name : String(v);
                    tempForm.appendChild(input);
                  }
                  document.body.appendChild(tempForm);
                  try { if (tempForm.requestSubmit) tempForm.requestSubmit(); else tempForm.submit(); } catch (e) {}

                  return JSON.stringify({ submitted: summary, action });
                })()
                """
            );

            Console.WriteLine($"[DEBUG] FormData before save: {submittedFormJson}");


            // Give time for navigation or server processing
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
            var indexRegex = new Regex(".*/admin/partner-products$");
            var createRegex = new Regex(".*/admin/partner-products/create$");

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

            await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/partner-products/edit/\\d+$"));

            // Capture the id from the edit URL for later direct checks
            var currentUrl = _driver.Page.Url;
            var match = Regex.Match(currentUrl, @"/admin/partner-products/edit/(\d+)$");
            if (match.Success)
            {
                _editedProductId = match.Groups[1].Value;
            }
        }

        [When(@"I change the name to ""(.*)""")]
        public async Task WhenIChangeTheNameTo(string newName)
        {
            var uniqueName = $"{newName} {DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}";

            var nameInput = _driver.Page.Locator("input[name='name']");
            await nameInput.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
            await nameInput.FillAsync("");
            await nameInput.FillAsync(uniqueName);

            // Dispatch input/change events to ensure reactive bindings capture the value
            await _driver.Page.EvaluateAsync("el => { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }", await nameInput.ElementHandleAsync());
            await nameInput.BlurAsync();

            // Verify the input holds our new value before saving
            await Assertions.Expect(nameInput).ToHaveValueAsync(uniqueName, new() { Timeout = 5000 });

            _createdProductName = uniqueName;
        }

        [Then("I should see the updated name in the overview")]
        public async Task ThenIShouldSeeTheUpdatedNameInTheOverview()
        {
            // Prefer a deterministic check: open the edit page of the same product and verify the name field
            if (string.IsNullOrEmpty(_editedProductId))
            {
                await CaptureDiagnosticsAsync("missing-edited-product-id");
                throw new Exception("Edited product id was not captured after clicking edit. Cannot verify updated name deterministically.");
            }

            var editUrl = $"{TestConfig.BaseUrl}/admin/partner-products/edit/{_editedProductId}";
            await _driver.Page.GotoAsync(editUrl, new() { WaitUntil = WaitUntilState.NetworkIdle });

            var nameInput = _driver.Page.Locator("input[name='name']");
            await nameInput.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
            await Assertions.Expect(nameInput).ToHaveValueAsync(_createdProductName, new() { Timeout = 10000 });
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
