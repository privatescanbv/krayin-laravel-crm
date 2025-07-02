Feature: Lead aanmaken via CRM en workflow triggeren in n8n

    Scenario: Nieuwe lead wordt aangemaakt voor privatescan
        Given ik ben op de CRM lead pagina
        When ik vul "Naam" in met "Test Lead"
        When ik vul "lead_source_id" in met "1"
        And ik klik op "Opslaan"
        Then moet er een actie zijn aangemaakt met titel "Klant data bijwerken"
        And de status in CRM moet zijn aangepast naar "1"

    Scenario: Nieuwe lead wordt aangemaakt voor hernia
        Given ik ben op de CRM lead pagina
        When ik vul "Naam" in met "Test Lead"
        When ik vul "lead_source_id" in met "2"
        And ik klik op "Opslaan"
        Then moet er een actie zijn aangemaakt met titel "Klant data bijwerken"
        And de status in CRM moet zijn aangepast naar "2"
