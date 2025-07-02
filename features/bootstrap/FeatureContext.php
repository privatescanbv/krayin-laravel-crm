<?php

use Behat\Behat\Context\Context;
use GuzzleHttp\Client;

class FeatureContext implements Context
{
    private Client $client;

    private array $leadData;

    private $leadId;

    private $response;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri'    => 'http://crm', // docker-compose service name
            'http_errors' => false,
        ]);
    }

    /**
     * @Given ik ben op de CRM lead pagina
     */
    public function ikBenOpDeCrmLeadPagina()
    {
        // Simulatie: niks nodig voor API-based tests
    }

    /**
     * @When ik vul :veld in met :waarde
     */
    public function ikVulInMet($veld, $waarde)
    {
        $this->leadData[$veld] = $waarde;
    }

    /**
     * @When ik klik op :button
     */
    public function ikKlikOp($button)
    {
        // Lead aanmaken via API
        $response = $this->client->post('/api/leads', [
            'json' => [
                'first_name'     => $this->leadData['Naam'] ?? 'Integration test',
                'last_name'      => 'Integration lastname',
                'email'          => 'bart@example.com',
                'phone'          => '0612345678',
                'company_name'   => 'Woonkamp B.V.',
                'lead_source'    => 'Website',
                'title'          => 'lead tel (hernia)',
                'lead_source_id' => $this->leadData['lead_source_id'] ?? 2,
                'tags'           => ['crm', 'n8n'],
            ],
        ]);

        $this->response = $response;

        $status = $response->getStatusCode();
        $bodyText = $response->getBody()->getContents();
        $body = json_decode($bodyText, true);

        if ($status < 200 || $status >= 300 || ! isset($body['data']['id'])) {
            throw new Exception(
                "Lead aanmaken mislukt:\n".
                "→ HTTP-status: {$status}\n".
                "→ Response body:\n{$bodyText}"
            );
        }

        $this->leadId = $body['data']['id'];
    }

    /**
     * @Then /^moet er een actie zijn aangemaakt met titel "([^"]*)"$/
     */
    public function moetErEenActieZijnAangemaaktMetTitel($taskTitle)
    {
        ini_set('pcre.backtrack_limit', '10000000');

        if (empty($this->leadId)) {
            throw new Exception('Lead ID is niet gezet — waarschijnlijk is het aanmaken van de lead mislukt.');
        }
        sleep(5);

        // Simpele controle: wacht en controleer CRM-activiteit
        $found = false;
        for ($i = 0; $i < 5; $i++) {
            sleep(1);
            $url = '/api/leads/'.$this->leadId.'/activities';
            $response = $this->client->get($url);
            $body = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            $data = json_decode($body, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Exception(
                    "Lead activities opvragen mislukt:\n".
                    "→ HTTP-status: {$statusCode}\n".
                    "→ Response body:\n{$body}"
                );
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Ongeldige JSON ontvangen:\n{$body}");
            }
            if (empty($data['data'])) {
                continue;
            }
            // Check op activiteit met title 'Klant data bijwerken'
            foreach ($data['data'] as $activity) {
                if (($activity['title'] ?? '') === $taskTitle) {
                    $found = true;
                    break 2;
                }
            }
        }
        if (! $found) {
            throw new Exception("Geen activiteit gevonden met de titel 'Klant data bijwerken'.\nLaatste response:\n".json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    /**
     * @Then de status in CRM moet zijn aangepast naar :status
     */
    public function deStatusInCrmMoetZijnAangepastNaar($leadStatus)
    {
        $url = "/api/leads/{$this->leadId}";
        $response = $this->client->get($url);
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $bodyJson = json_decode($body, true);
        $pipelineStageId = $bodyJson['data']['lead_pipeline_stage_id'] ?? null;
        if ($statusCode < 200 || $statusCode >= 300 || is_null($pipelineStageId)) {
            throw new Exception(
                "Ophalen lead mislukt:\n".
                "→ URL: {$url}\n".
                "→ HTTP-status: {$statusCode}\n".
                "→ Response body:\n{$body}"
            );
        }
        if (($pipelineStageId ?? '') !== $leadStatus) {
            throw new Exception("Status is niet '{$leadStatus}', maar '{$pipelineStageId}'"."→ Response body:\n{$body}");
        }
    }
}
