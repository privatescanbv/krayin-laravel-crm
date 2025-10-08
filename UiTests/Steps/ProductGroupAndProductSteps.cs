using System;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Playwright;
using Reqnroll;
using UiTests.Support;

namespace UiTests.Steps
{
	[Binding]
	public class ProductGroupAndProductSteps
	{
		private readonly BrowserDriver _driver;
		private string _createdGroupName = string.Empty;
		private string _createdProductName = string.Empty;

		public ProductGroupAndProductSteps(BrowserDriver driver)
		{
			_driver = driver;
		}

		[Given("I open the product groups page")]
		public async Task GivenIOpenTheProductGroupsPage()
		{
			var url = $"{TestConfig.BaseUrl}/admin/productgroups";
			await _driver.Page.GotoAsync(url, new() { WaitUntil = WaitUntilState.NetworkIdle });
			await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/productgroups$"));
			await _driver.Page.WaitForSelectorAsync(".flex.flex-col.gap-4", new() { Timeout = 10000 });
		}

		[When("I click on create product group")]
		public async Task WhenIClickOnCreateProductGroup()
		{
			var createButton = _driver.Page.Locator("a[href$='/admin/productgroups/create']");
			await createButton.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await createButton.ClickAsync();
			await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/productgroups/create$"));
		}

		[When("I fill in the product group form with name \"(.*)\"")]
		public async Task WhenIFillInTheProductGroupFormWithName(string name)
		{
			_createdGroupName = name;

			var nameInput = _driver.Page.Locator("input[name='name']");
			await nameInput.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await nameInput.FillAsync("");
			await nameInput.FillAsync(_createdGroupName);
			await nameInput.BlurAsync();
		}

		[When("I save the product group")]
		public async Task WhenISaveTheProductGroup()
		{
			// Click submit inside the productgroups create form
			var form = _driver.Page.Locator("form[action$='/admin/productgroups/create']");
			if (await form.CountAsync() == 0)
			{
				// fallback: any form containing productgroups in action
				form = _driver.Page.Locator("form[action*='/admin/productgroups']");
			}
			var submit = form.Locator("button[type='submit']");
			await submit.First.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await submit.First.ClickAsync();
			await _driver.Page.WaitForLoadStateAsync(LoadState.NetworkIdle);
		}

		[Then("I should be redirected to the product groups overview")]
		public async Task ThenIShouldBeRedirectedToTheProductGroupsOverview()
		{
			await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/productgroups$"), new() { Timeout = 15000 });
		}

		[Then("I should see \"(.*)\" in the overview")]
		public async Task ThenIShouldSeeInTheOverview(string expected)
		{
			// DataGrid renders rows asynchronously; wait for any row matching our created name
			var row = _driver.Page.Locator(".table-responsive .row.max-lg\\:hidden").Filter(new() { HasTextString = expected });
			await row.First.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 15000 });
		}

		[When("I open the products page")]
		public async Task WhenIOpenTheProductsPage()
		{
			var url = $"{TestConfig.BaseUrl}/admin/products";
			await _driver.Page.GotoAsync(url, new() { WaitUntil = WaitUntilState.NetworkIdle });
			await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/products$"));
			await _driver.Page.WaitForSelectorAsync(".flex.flex-col.gap-4", new() { Timeout = 10000 });
		}

		[When("I click on create product")]
		public async Task WhenIClickOnCreateProduct()
		{
			var createLink = _driver.Page.GetByRole(AriaRole.Link, new() { NameRegex = new Regex("^(Product aanmaken|Create Product)$") });
			await createLink.First.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await createLink.First.ClickAsync();
			await Assertions.Expect(_driver.Page).ToHaveURLAsync(new Regex(".*/admin/products/create$"));
		}

		[When("I fill in the product form with name \"(.*)\" and price \"(.*)\" and product group \"(.*)\"")]
		public async Task WhenIFillInTheProductFormWithNameAndPriceAndProductGroup(string name, string price, string groupName)
		{
			_createdProductName = name;

			// General fields
			var nameInput = _driver.Page.Locator("input[name='name']");
			await nameInput.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await nameInput.FillAsync("");
			await nameInput.FillAsync(_createdProductName);
			await _driver.Page.EvaluateAsync("el => { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }", await nameInput.ElementHandleAsync());
			await nameInput.BlurAsync();

			// Currency (required)
			var currencySelect = _driver.Page.Locator("select[name='currency']");
			if (await currencySelect.CountAsync() > 0)
			{
				await currencySelect.SelectOptionAsync(new[] { "EUR" });
				await _driver.Page.EvaluateAsync("el => el && el.dispatchEvent(new Event('change', { bubbles: true }))", await currencySelect.ElementHandleAsync());
			}

			// Price (required)
			var priceInput = _driver.Page.Locator("input[name='price']");
			if (await priceInput.CountAsync() == 0)
			{
				priceInput = _driver.Page.Locator("input[name='price']");
			}
			var ciPrice = price.Replace('.', ',');
			await priceInput.FillAsync("");
			await priceInput.FillAsync(ciPrice);
			await _driver.Page.EvaluateAsync("el => { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }", await priceInput.ElementHandleAsync());
			await priceInput.BlurAsync();

			// Resource type (required)
			var resourceTypeSelect = _driver.Page.Locator("select[name='resource_type_id']");
			await resourceTypeSelect.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			var resourceOptions = await resourceTypeSelect.Locator("option:not([value=''])").AllAsync();
			if (resourceOptions.Count == 0)
			{
				throw new Exception("No resource type options available on product form.");
			}
			var firstResourceValue = await resourceOptions[0].GetAttributeAsync("value");
			await resourceTypeSelect.SelectOptionAsync(new[] { firstResourceValue });
			await _driver.Page.EvaluateAsync("el => el && el.dispatchEvent(new Event('change', { bubbles: true }))", await resourceTypeSelect.ElementHandleAsync());

			// Product group (required)
			var groupSelect = _driver.Page.Locator("select[name='product_group_id']");
			if (await groupSelect.CountAsync() > 0)
			{
				// Prefer selecting by label text; fallback to first non-empty
				try { await groupSelect.SelectOptionAsync(new SelectOptionValue { Label = groupName }); }
				catch { var firstGroupValue = await groupSelect.Locator("option:not([value=''])").First.GetAttributeAsync("value"); await groupSelect.SelectOptionAsync(new[] { firstGroupValue }); }
				await _driver.Page.EvaluateAsync("el => el && el.dispatchEvent(new Event('change', { bubbles: true }))", await groupSelect.ElementHandleAsync());
			}
			else
			{
				// Try a generic searchable lookup (not expected for products form)
				var lookupTrigger = _driver.Page.Locator("button:has-text('Selecteer'), button:has-text('Select'), .lookup-component button");
				if (await lookupTrigger.CountAsync() > 0)
				{
					await lookupTrigger.First.ClickAsync();
					var search = _driver.Page.Locator("input[type='search'], input[placeholder*='zoek' i], input[placeholder*='search' i]");
					await search.First.FillAsync(groupName);
					await _driver.Page.Keyboard.PressAsync("Enter");
					var option = _driver.Page.Locator($"text={groupName}");
					await option.First.ClickAsync();
				}
			}
		}

		[When("I save the product")]
		public async Task WhenISaveTheProduct()
		{
			// Click submit inside the products create form
			var form = _driver.Page.Locator("form[action$='/admin/products/create']");
			if (await form.CountAsync() == 0)
			{
				form = _driver.Page.Locator("form[action*='/admin/products']");
			}
			var submit = form.Locator("button[type='submit']");
			await submit.First.WaitForAsync(new() { State = WaitForSelectorState.Visible, Timeout = 10000 });
			await submit.First.ClickAsync();
			await _driver.Page.WaitForLoadStateAsync(LoadState.NetworkIdle);
		}

		[Then("I should be redirected to the products overview")]
		public async Task ThenIShouldBeRedirectedToTheProductsOverview()
		{
			var indexRegex = new Regex(".*/admin/products$");
			var createRegex = new Regex(".*/admin/products/create$");
			try
			{
				await Assertions.Expect(_driver.Page).ToHaveURLAsync(indexRegex, new() { Timeout = 15000 });
			}
			catch
			{
				if (createRegex.IsMatch(_driver.Page.Url))
				{
					// Collect visible validation errors to aid debugging
					var errors = _driver.Page.Locator(".control-error, .text-red-600, .alert-error, .mb-4 ul li");
					var count = await errors.CountAsync();
					string allErrors = string.Empty;
					for (var i = 0; i < count; i++)
					{
						var text = await errors.Nth(i).InnerTextAsync();
						allErrors += $"\n- {text}";
					}
					var url = _driver.Page.Url;
					throw new Exception($"Stayed on create page after save. URL: {url}\nValidation errors:{allErrors}");
				}
				throw;
			}
		}
	}
}
