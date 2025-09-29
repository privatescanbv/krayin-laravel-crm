Feature: Login

    Scenario: User can login with valid credentials
        Given I open the login page
        When I enter username "mark.bulthuis@privatescan.nl" and password "8AAZ5jc%e&AF"
        And I click the login button
        Then I should see the dashboard
