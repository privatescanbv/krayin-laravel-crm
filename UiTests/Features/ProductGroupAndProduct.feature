Feature: Productgroep en Product beheren

    Background:
        Given I am logged in

    Scenario: Aanmaken van productgroep en daarna product
        Given I open the product groups page
        When I click on create product group
        And I fill in the product group form with name "Test Productgroep"
        And I save the product group
        Then I should be redirected to the product groups overview
        And I should see "Test Productgroep" in the overview
        When I open the products page
        And I click on create product
        And I fill in the product form with name "Test Product" and price "99.99" and product group "Test Productgroep"
        And I save the product
        Then I should be redirected to the products overview
        And I should see "Test Product" in the overview

