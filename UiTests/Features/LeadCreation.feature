Feature: Lead aanmaken

    Background:
        Given I am logged in

    Scenario: Aanmaken van lead zonder person te kiezen in stap 1
        Given I open the lead create page
        When I go to step 2 without selecting a person
        And I fill the required lead fields
        And I save the lead
        Then I should be redirected to the lead view page

    Scenario: Aanmaken van lead met een person te kiezen in stap 1
        Given I open the lead create page
        When I select the first person suggestion for query "test"
        And I go to step 2
        And I fill the required lead fields
        And I save the lead
        Then I should be redirected to the lead view page

