Feature: Partner Product beheren

    Background:
        Given I am logged in

    Scenario: Aanmaken van partner product en prijs aanpassen
        Given I open the partner products page
        When I click on create partner product
        And I fill in the partner product form with name "Test Partnerproduct" and price "150.00"
        And I save the partner product
        Then I should be redirected to the partner products overview
        When I edit the first partner product
        And I change the price to "175.50"
        And I save the partner product
        Then I should see the updated price in the overview