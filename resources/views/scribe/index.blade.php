<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Privatescan CRM API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "https://crm.local.privatescan.nl";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.6.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.6.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">

            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>

    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-POSTapi-leads-hernia">
                                <a href="#endpoints-POSTapi-leads-hernia">Create a Hernia lead from the inbound (Gravity Forms) payload schema.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-leads-privatescan">
                                <a href="#endpoints-POSTapi-leads-privatescan">Create a Privatescan lead from the inbound (Web-to-person) payload schema.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-leads">
                                <a href="#endpoints-POSTapi-leads">Store a newly created lead in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-leads--id-">
                                <a href="#endpoints-GETapi-leads--id-">Display the specified lead.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-leads--id-">
                                <a href="#endpoints-PUTapi-leads--id-">Update the specified lead in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PATCHapi-leads--id--stage">
                                <a href="#endpoints-PATCHapi-leads--id--stage">Update the pipeline stage of a lead.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PATCHapi-leads--id--next_stage">
                                <a href="#endpoints-PATCHapi-leads--id--next_stage">PATCH api/leads/{id}/next_stage</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-leads--id-">
                                <a href="#endpoints-DELETEapi-leads--id-">Remove the specified lead from storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-leads--leadId--notes">
                                <a href="#endpoints-POSTapi-leads--leadId--notes">Add a note to a lead.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-leads--id--activities">
                                <a href="#endpoints-POSTapi-leads--id--activities">Store a newly created activity in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-leads--id--activities">
                                <a href="#endpoints-GETapi-leads--id--activities">Display a listing of the resource.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-groups-byDepartment--departmentName-">
                                <a href="#endpoints-GETapi-groups-byDepartment--departmentName-">Relations group < - > department is based on the same name.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-sales-leads">
                                <a href="#endpoints-POSTapi-sales-leads">Store a newly created workflow lead via API.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-sales-leads--id--activities">
                                <a href="#endpoints-GETapi-sales-leads--id--activities">List activities for a sales lead.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-sales-leads--id--activities">
                                <a href="#endpoints-POSTapi-sales-leads--id--activities">Create an activity attached to a sales.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-webhooks-event">
                                <a href="#endpoints-PUTapi-webhooks-event">PUT api/webhooks/event</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-keycloak-webhooks">
                                <a href="#endpoints-POSTapi-keycloak-webhooks">POST api/keycloak/webhooks</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-keycloak-persons--keycloakUserId-">
                                <a href="#endpoints-GETapi-keycloak-persons--keycloakUserId-">Haal person id op op basis van Keycloak user id.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-patient--id--messages">
                                <a href="#endpoints-GETapi-patient--id--messages">Get all patient messages for a person, grouped by thread.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-patient--id--messages">
                                <a href="#endpoints-POSTapi-patient--id--messages">Store a new patient message or reply.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-patient--id--activities-unread-count">
                                <a href="#endpoints-GETapi-patient--id--activities-unread-count">Get the count of unread messages for a specific person.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-patient-appointments" class="tocify-header">
                <li class="tocify-item level-1" data-unique="patient-appointments">
                    <a href="#patient-appointments">Patient appointments</a>
                </li>
                                    <ul id="tocify-subheader-patient-appointments" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="patient-appointments-GETapi-patient--id--appointments">
                                <a href="#patient-appointments-GETapi-patient--id--appointments">Get appointments for a patient (derived from Orders).</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-patient-messages" class="tocify-header">
                <li class="tocify-item level-1" data-unique="patient-messages">
                    <a href="#patient-messages">Patient messages</a>
                </li>
                                    <ul id="tocify-subheader-patient-messages" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="patient-messages-PUTapi-patient--id--messages-mark_as_read">
                                <a href="#patient-messages-PUTapi-patient--id--messages-mark_as_read">Mark all messages as read by patient (not employee)</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: January 27, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<aside>
    <strong>Base URL</strong>: <code>https://crm.local.privatescan.nl</code>
</aside>
<pre><code>This documentation aims to provide all the information you need to work with our API.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include a <strong><code>X-API-KEY</code></strong> header with the value <strong><code>"{YOUR_AUTH_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Send your API key in the <code>X-API-KEY</code> header (or authenticate with a Keycloak <code>Authorization: Bearer &lt;token&gt;</code> token).</p>

        <h1 id="endpoints">Endpoints</h1>



                                <h2 id="endpoints-POSTapi-leads-hernia">Create a Hernia lead from the inbound (Gravity Forms) payload schema.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-leads-hernia">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/leads/hernia" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"campaign_id\": \"69b238c0-e630-b733-2bb3-4fd85ff554da\",
    \"lead_source\": \"Herniapoli.nl\",
    \"kanaal_c\": \"website\",
    \"soort_aanvraag_c\": \"operatie\",
    \"salutation\": \"Dhr.\",
    \"first_name\": \"architecto\",
    \"last_name\": \"architecto\",
    \"birthdate\": \"2026-01-27\",
    \"email1\": \"zbailey@example.net\",
    \"phone_mobile\": \"0612345678\",
    \"primary_huisnr_c\": \"12\",
    \"primary_huisnr_toevoeging_c\": \"architecto\",
    \"primary_address_postalcode\": \"1234AB\",
    \"description\": \"Eius et animi quos velit et.\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/hernia"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "campaign_id": "69b238c0-e630-b733-2bb3-4fd85ff554da",
    "lead_source": "Herniapoli.nl",
    "kanaal_c": "website",
    "soort_aanvraag_c": "operatie",
    "salutation": "Dhr.",
    "first_name": "architecto",
    "last_name": "architecto",
    "birthdate": "2026-01-27",
    "email1": "zbailey@example.net",
    "phone_mobile": "0612345678",
    "primary_huisnr_c": "12",
    "primary_huisnr_toevoeging_c": "architecto",
    "primary_address_postalcode": "1234AB",
    "description": "Eius et animi quos velit et."
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-leads-hernia">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Lead created successfully.&quot;,
    &quot;lead_id&quot;: 123,
    &quot;data&quot;: {
        &quot;id&quot;: 123
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-leads-hernia" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-leads-hernia"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-leads-hernia"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-leads-hernia" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-leads-hernia">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-leads-hernia" data-method="POST"
      data-path="api/leads/hernia"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-leads-hernia', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-leads-hernia"
                    onclick="tryItOut('POSTapi-leads-hernia');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-leads-hernia"
                    onclick="cancelTryOut('POSTapi-leads-hernia');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-leads-hernia"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/leads/hernia</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-leads-hernia"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-leads-hernia"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-leads-hernia"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>campaign_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="campaign_id"                data-endpoint="POSTapi-leads-hernia"
               value="69b238c0-e630-b733-2bb3-4fd85ff554da"
               data-component="body">
    <br>
<p>Marketing campaign external id (UUID). Dit is <strong>niet</strong> de numerieke database id, maar <code>marketing_campaigns.external_id</code> (model: <code>Webkul\Marketing\Models\Campaign</code>). The <code>external_id</code> of an existing record in the marketing_campaigns table. Example: <code>69b238c0-e630-b733-2bb3-4fd85ff554da</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>lead_source</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="lead_source"                data-endpoint="POSTapi-leads-hernia"
               value="Herniapoli.nl"
               data-component="body">
    <br>
<p>Broncode (string) die gemapt wordt naar lead_source_id. Zelfde mapping als in <code>InboundLeadPayloadMapper::mapLeadSourceId()</code>. Bij geen match: default naar &quot;Anders&quot; (lead_source_id=32). Example: <code>Herniapoli.nl</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>kanaal_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="kanaal_c"                data-endpoint="POSTapi-leads-hernia"
               value="website"
               data-component="body">
    <br>
<p>Kanaal (string) die gemapt wordt naar lead_channel_id. Ondersteunde waarden: telefoon, website, email (of e-mail), tel-en-tel, agenten, partners, social media (of socialmedia), webshop, campagne. Bij geen match: default naar Website (lead_channel_id=2). Example: <code>website</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>soort_aanvraag_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="soort_aanvraag_c"                data-endpoint="POSTapi-leads-hernia"
               value="operatie"
               data-component="body">
    <br>
<p>Type aanvraag (string) die gemapt wordt naar lead_type_id. Ondersteunde waarden: preventie (1), gericht (2), operatie (3), overig (4). Bij geen match: default naar Overig (lead_type_id=4). Example: <code>operatie</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>salutation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="salutation"                data-endpoint="POSTapi-leads-hernia"
               value="Dhr."
               data-component="body">
    <br>
<p>Enum-achtige waarde. Toegestane waarden: &quot;Dhr.&quot;, &quot;Mevr.&quot; (ook &quot;Mr.&quot;/&quot;Mrs.&quot; wordt geaccepteerd en omgezet). Bij geen match: validatie faalt (422) omdat de lead-validatie alleen &quot;Dhr.&quot;/&quot;Mevr.&quot; accepteert. Example: <code>Dhr.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>first_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="first_name"                data-endpoint="POSTapi-leads-hernia"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>last_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="last_name"                data-endpoint="POSTapi-leads-hernia"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>birthdate</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="birthdate"                data-endpoint="POSTapi-leads-hernia"
               value="2026-01-27"
               data-component="body">
    <br>
<p>Must be a valid date in the format <code>Y-m-d</code>. Example: <code>2026-01-27</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email1</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email1"                data-endpoint="POSTapi-leads-hernia"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone_mobile</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone_mobile"                data-endpoint="POSTapi-leads-hernia"
               value="0612345678"
               data-component="body">
    <br>
<p>Telefoonnummer. Wordt genormaliseerd naar E.164 (bv 0612345678 → +31612345678) vóór validatie. Example: <code>0612345678</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>primary_huisnr_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="primary_huisnr_c"                data-endpoint="POSTapi-leads-hernia"
               value="12"
               data-component="body">
    <br>
<p>Huisnummer (optioneel). Example: <code>12</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>primary_huisnr_toevoeging_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="primary_huisnr_toevoeging_c"                data-endpoint="POSTapi-leads-hernia"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>primary_address_postalcode</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="primary_address_postalcode"                data-endpoint="POSTapi-leads-hernia"
               value="1234AB"
               data-component="body">
    <br>
<p>Postcode (optioneel). Example: <code>1234AB</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-leads-hernia"
               value="Eius et animi quos velit et."
               data-component="body">
    <br>
<p>Example: <code>Eius et animi quos velit et.</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-leads-privatescan">Create a Privatescan lead from the inbound (Web-to-person) payload schema.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-leads-privatescan">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/leads/privatescan" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"lead_source\": \"privatescannl\",
    \"kanaal_c\": \"website\",
    \"soort_aanvraag_c\": \"preventie\",
    \"salutation\": \"Mr.\",
    \"first_name\": \"architecto\",
    \"last_name\": \"architecto\",
    \"email\": \"zbailey@example.net\",
    \"phone\": \"0611111111\",
    \"description\": \"Eius et animi quos velit et.\",
    \"url\": \"http:\\/\\/www.ernser.org\\/harum-mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html\",
    \"section\": \"architecto\",
    \"select_verzoek\": \"architecto\",
    \"select_interesse\": \"architecto\",
    \"campaign_id\": \"69b238c0-e630-b733-2bb3-4fd85ff554da\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/privatescan"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "lead_source": "privatescannl",
    "kanaal_c": "website",
    "soort_aanvraag_c": "preventie",
    "salutation": "Mr.",
    "first_name": "architecto",
    "last_name": "architecto",
    "email": "zbailey@example.net",
    "phone": "0611111111",
    "description": "Eius et animi quos velit et.",
    "url": "http:\/\/www.ernser.org\/harum-mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html",
    "section": "architecto",
    "select_verzoek": "architecto",
    "select_interesse": "architecto",
    "campaign_id": "69b238c0-e630-b733-2bb3-4fd85ff554da"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-leads-privatescan">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Lead created successfully.&quot;,
    &quot;lead_id&quot;: 123,
    &quot;data&quot;: {
        &quot;id&quot;: 123
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-leads-privatescan" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-leads-privatescan"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-leads-privatescan"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-leads-privatescan" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-leads-privatescan">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-leads-privatescan" data-method="POST"
      data-path="api/leads/privatescan"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-leads-privatescan', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-leads-privatescan"
                    onclick="tryItOut('POSTapi-leads-privatescan');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-leads-privatescan"
                    onclick="cancelTryOut('POSTapi-leads-privatescan');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-leads-privatescan"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/leads/privatescan</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-leads-privatescan"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-leads-privatescan"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-leads-privatescan"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>lead_source</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="lead_source"                data-endpoint="POSTapi-leads-privatescan"
               value="privatescannl"
               data-component="body">
    <br>
<p>Broncode (string) die gemapt wordt naar lead_source_id. Ondersteunde waarden o.a.: bodyscannl, privatescannl, mriscannl, ccsvionlinenl, ccsvionlinecom, bodyscan.nl, privatescan.nl, mri-scan.nl, ccsvi-online.nl, ccsvi-online.com, google zoeken, adwords, krant telegraaf, krant spits, krant regionaal, krant overige dagbladen, krant redactioneel, magazine dito, magazine humo belgie, dokterdokter.nl, vrouw.nl, dito-magazine.nl, groupdeal.nl, marktplaats, zorgplanet.nl, linkpartner, youtube, linkedin, twitter, facebook, rtl business class, nieuwsbrief, bestaande klant, zakenrelatie, vrienden, familie, kennissen, collega, anders, wegener webshop, herniapoli.nl. Bij geen match: default naar &quot;Anders&quot; (lead_source_id=32). Example: <code>privatescannl</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>kanaal_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="kanaal_c"                data-endpoint="POSTapi-leads-privatescan"
               value="website"
               data-component="body">
    <br>
<p>Kanaal (string) die gemapt wordt naar lead_channel_id. Ondersteunde waarden: telefoon, website, email (of e-mail), tel-en-tel, agenten, partners, social media (of socialmedia), webshop, campagne. Bij geen match: default naar Website (lead_channel_id=2). Example: <code>website</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>soort_aanvraag_c</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="soort_aanvraag_c"                data-endpoint="POSTapi-leads-privatescan"
               value="preventie"
               data-component="body">
    <br>
<p>Type aanvraag. Voor deze endpoint alleen &quot;preventie&quot; (wordt lead_type_id=1). Bij geen match: default naar Overig (lead_type_id=4). Example: <code>preventie</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>preventie</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>salutation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="salutation"                data-endpoint="POSTapi-leads-privatescan"
               value="Mr."
               data-component="body">
    <br>
<p>Enum-achtige waarde. Toegestane waarden: &quot;Mr.&quot;, &quot;Mrs.&quot; of false. Mapping: &quot;Mr.&quot; → &quot;Dhr.&quot;, &quot;Mrs.&quot; → &quot;Mevr.&quot;. Bij false/null: geen aanspreekvorm (salutation wordt leeg). Example: <code>Mr.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>first_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="first_name"                data-endpoint="POSTapi-leads-privatescan"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>last_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="last_name"                data-endpoint="POSTapi-leads-privatescan"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-leads-privatescan"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-leads-privatescan"
               value="0611111111"
               data-component="body">
    <br>
<p>Telefoonnummer. Wordt genormaliseerd naar E.164 (bv 0612345678 → +31612345678) vóór validatie. Example: <code>0611111111</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>assigned_user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="assigned_user_id"                data-endpoint="POSTapi-leads-privatescan"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-leads-privatescan"
               value="Eius et animi quos velit et."
               data-component="body">
    <br>
<p>Example: <code>Eius et animi quos velit et.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="url"                data-endpoint="POSTapi-leads-privatescan"
               value="http://www.ernser.org/harum-mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html"
               data-component="body">
    <br>
<p>Must be a valid URL. Example: <code>http://www.ernser.org/harum-mollitia-modi-deserunt-aut-ab-provident-perspiciatis-quo.html</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>section</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="section"                data-endpoint="POSTapi-leads-privatescan"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>select_verzoek</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="select_verzoek"                data-endpoint="POSTapi-leads-privatescan"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>select_interesse</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="select_interesse"                data-endpoint="POSTapi-leads-privatescan"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>personen</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="personen"                data-endpoint="POSTapi-leads-privatescan"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>campaign_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="campaign_id"                data-endpoint="POSTapi-leads-privatescan"
               value="69b238c0-e630-b733-2bb3-4fd85ff554da"
               data-component="body">
    <br>
<p>Marketing campaign external id (UUID). Dit is <strong>niet</strong> de numerieke database id, maar <code>marketing_campaigns.external_id</code> (model: <code>Webkul\Marketing\Models\Campaign</code>). Wordt vaak gezet vanuit een cookie/UTM id. The <code>external_id</code> of an existing record in the marketing_campaigns table. Example: <code>69b238c0-e630-b733-2bb3-4fd85ff554da</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-leads">Store a newly created lead in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-leads">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/leads" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-leads">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Lead created successfully.&quot;,
    &quot;lead_id&quot;: 123,
    &quot;data&quot;: {
        &quot;id&quot;: 123
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-leads" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-leads"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-leads"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-leads" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-leads">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-leads" data-method="POST"
      data-path="api/leads"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-leads', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-leads"
                    onclick="tryItOut('POSTapi-leads');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-leads"
                    onclick="cancelTryOut('POSTapi-leads');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-leads"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/leads</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-leads"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-leads"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-leads"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="POSTapi-leads"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the users table.</p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-leads--id-">Display the specified lead.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-leads--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/leads/architecto" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-leads--id-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 59
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-leads--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-leads--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-leads--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-leads--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-leads--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-leads--id-" data-method="GET"
      data-path="api/leads/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-leads--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-leads--id-"
                    onclick="tryItOut('GETapi-leads--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-leads--id-"
                    onclick="cancelTryOut('GETapi-leads--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-leads--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/leads/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-leads--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-leads--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-leads--id-">Update the specified lead in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-leads--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://crm.local.privatescan.nl/api/leads/architecto" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PUT",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-leads--id-">
</span>
<span id="execution-results-PUTapi-leads--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-leads--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-leads--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-leads--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-leads--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-leads--id-" data-method="PUT"
      data-path="api/leads/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-leads--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-leads--id-"
                    onclick="tryItOut('PUTapi-leads--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-leads--id-"
                    onclick="cancelTryOut('PUTapi-leads--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-leads--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/leads/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="PUTapi-leads--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-leads--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="PUTapi-leads--id-"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the users table.</p>
        </div>
        </form>

                    <h2 id="endpoints-PATCHapi-leads--id--stage">Update the pipeline stage of a lead.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-leads--id--stage">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://crm.local.privatescan.nl/api/leads/architecto/stage" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"lead_pipeline_stage_id\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto/stage"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "lead_pipeline_stage_id": "architecto"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-leads--id--stage">
</span>
<span id="execution-results-PATCHapi-leads--id--stage" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-leads--id--stage"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-leads--id--stage"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-leads--id--stage" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-leads--id--stage">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-leads--id--stage" data-method="PATCH"
      data-path="api/leads/{id}/stage"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-leads--id--stage', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-leads--id--stage"
                    onclick="tryItOut('PATCHapi-leads--id--stage');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-leads--id--stage"
                    onclick="cancelTryOut('PATCHapi-leads--id--stage');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-leads--id--stage"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/leads/{id}/stage</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="PATCHapi-leads--id--stage"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-leads--id--stage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-leads--id--stage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-leads--id--stage"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>lead_pipeline_stage_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="lead_pipeline_stage_id"                data-endpoint="PATCHapi-leads--id--stage"
               value="architecto"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the lead_pipeline_stages table. Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="endpoints-PATCHapi-leads--id--next_stage">PATCH api/leads/{id}/next_stage</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-leads--id--next_stage">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "https://crm.local.privatescan.nl/api/leads/architecto/next_stage" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto/next_stage"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PATCHapi-leads--id--next_stage">
</span>
<span id="execution-results-PATCHapi-leads--id--next_stage" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-leads--id--next_stage"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-leads--id--next_stage"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-leads--id--next_stage" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-leads--id--next_stage">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-leads--id--next_stage" data-method="PATCH"
      data-path="api/leads/{id}/next_stage"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-leads--id--next_stage', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-leads--id--next_stage"
                    onclick="tryItOut('PATCHapi-leads--id--next_stage');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-leads--id--next_stage"
                    onclick="cancelTryOut('PATCHapi-leads--id--next_stage');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-leads--id--next_stage"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/leads/{id}/next_stage</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="PATCHapi-leads--id--next_stage"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-leads--id--next_stage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-leads--id--next_stage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PATCHapi-leads--id--next_stage"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-DELETEapi-leads--id-">Remove the specified lead from storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-leads--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "https://crm.local.privatescan.nl/api/leads/architecto" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-leads--id-">
</span>
<span id="execution-results-DELETEapi-leads--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-leads--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-leads--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-leads--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-leads--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-leads--id-" data-method="DELETE"
      data-path="api/leads/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-leads--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-leads--id-"
                    onclick="tryItOut('DELETEapi-leads--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-leads--id-"
                    onclick="cancelTryOut('DELETEapi-leads--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-leads--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/leads/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="DELETEapi-leads--id-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-leads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-leads--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-leads--leadId--notes">Add a note to a lead.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-leads--leadId--notes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/leads/architecto/notes" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto/notes"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-leads--leadId--notes">
</span>
<span id="execution-results-POSTapi-leads--leadId--notes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-leads--leadId--notes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-leads--leadId--notes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-leads--leadId--notes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-leads--leadId--notes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-leads--leadId--notes" data-method="POST"
      data-path="api/leads/{leadId}/notes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-leads--leadId--notes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-leads--leadId--notes"
                    onclick="tryItOut('POSTapi-leads--leadId--notes');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-leads--leadId--notes"
                    onclick="cancelTryOut('POSTapi-leads--leadId--notes');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-leads--leadId--notes"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/leads/{leadId}/notes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-leads--leadId--notes"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-leads--leadId--notes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-leads--leadId--notes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>leadId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="leadId"                data-endpoint="POSTapi-leads--leadId--notes"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-leads--id--activities">Store a newly created activity in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-leads--id--activities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/leads/architecto/activities" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto/activities"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-leads--id--activities">
</span>
<span id="execution-results-POSTapi-leads--id--activities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-leads--id--activities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-leads--id--activities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-leads--id--activities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-leads--id--activities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-leads--id--activities" data-method="POST"
      data-path="api/leads/{id}/activities"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-leads--id--activities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-leads--id--activities"
                    onclick="tryItOut('POSTapi-leads--id--activities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-leads--id--activities"
                    onclick="cancelTryOut('POSTapi-leads--id--activities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-leads--id--activities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/leads/{id}/activities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-leads--id--activities"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-leads--id--activities"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-leads--id--activities">Display a listing of the resource.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-leads--id--activities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/leads/architecto/activities" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/leads/architecto/activities"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-leads--id--activities">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 58
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-leads--id--activities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-leads--id--activities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-leads--id--activities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-leads--id--activities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-leads--id--activities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-leads--id--activities" data-method="GET"
      data-path="api/leads/{id}/activities"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-leads--id--activities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-leads--id--activities"
                    onclick="tryItOut('GETapi-leads--id--activities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-leads--id--activities"
                    onclick="cancelTryOut('GETapi-leads--id--activities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-leads--id--activities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/leads/{id}/activities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-leads--id--activities"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-leads--id--activities"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the lead. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-groups-byDepartment--departmentName-">Relations group &lt; - &gt; department is based on the same name.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-groups-byDepartment--departmentName-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/groups/byDepartment/architecto" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/groups/byDepartment/architecto"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-groups-byDepartment--departmentName-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 57
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-groups-byDepartment--departmentName-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-groups-byDepartment--departmentName-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-groups-byDepartment--departmentName-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-groups-byDepartment--departmentName-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-groups-byDepartment--departmentName-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-groups-byDepartment--departmentName-" data-method="GET"
      data-path="api/groups/byDepartment/{departmentName}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-groups-byDepartment--departmentName-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-groups-byDepartment--departmentName-"
                    onclick="tryItOut('GETapi-groups-byDepartment--departmentName-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-groups-byDepartment--departmentName-"
                    onclick="cancelTryOut('GETapi-groups-byDepartment--departmentName-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-groups-byDepartment--departmentName-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/groups/byDepartment/{departmentName}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-groups-byDepartment--departmentName-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-groups-byDepartment--departmentName-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-groups-byDepartment--departmentName-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>departmentName</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departmentName"                data-endpoint="GETapi-groups-byDepartment--departmentName-"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-sales-leads">Store a newly created workflow lead via API.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-sales-leads">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/sales-leads" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"b\",
    \"description\": \"Eius et animi quos velit et.\",
    \"lead_id\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/sales-leads"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "b",
    "description": "Eius et animi quos velit et.",
    "lead_id": "architecto"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-sales-leads">
</span>
<span id="execution-results-POSTapi-sales-leads" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-sales-leads"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-sales-leads"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-sales-leads" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-sales-leads">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-sales-leads" data-method="POST"
      data-path="api/sales-leads"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-sales-leads', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-sales-leads"
                    onclick="tryItOut('POSTapi-sales-leads');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-sales-leads"
                    onclick="cancelTryOut('POSTapi-sales-leads');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-sales-leads"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/sales-leads</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-sales-leads"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-sales-leads"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-sales-leads"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-sales-leads"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-sales-leads"
               value="Eius et animi quos velit et."
               data-component="body">
    <br>
<p>Example: <code>Eius et animi quos velit et.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pipeline_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pipeline_id"                data-endpoint="POSTapi-sales-leads"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the lead_pipelines table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pipeline_stage_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pipeline_stage_id"                data-endpoint="POSTapi-sales-leads"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the lead_pipeline_stages table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>lead_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="lead_id"                data-endpoint="POSTapi-sales-leads"
               value="architecto"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the leads table. Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="POSTapi-sales-leads"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the users table.</p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-sales-leads--id--activities">List activities for a sales lead.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-sales-leads--id--activities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/sales-leads/1/activities" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/sales-leads/1/activities"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-sales-leads--id--activities">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 56
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-sales-leads--id--activities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-sales-leads--id--activities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-sales-leads--id--activities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-sales-leads--id--activities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-sales-leads--id--activities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-sales-leads--id--activities" data-method="GET"
      data-path="api/sales-leads/{id}/activities"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-sales-leads--id--activities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-sales-leads--id--activities"
                    onclick="tryItOut('GETapi-sales-leads--id--activities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-sales-leads--id--activities"
                    onclick="cancelTryOut('GETapi-sales-leads--id--activities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-sales-leads--id--activities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/sales-leads/{id}/activities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-sales-leads--id--activities"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-sales-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-sales-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-sales-leads--id--activities"
               value="1"
               data-component="url">
    <br>
<p>The ID of the sales lead. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-sales-leads--id--activities">Create an activity attached to a sales.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-sales-leads--id--activities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/sales-leads/1/activities" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"type\": \"task\",
    \"title\": \"architecto\",
    \"description\": \"Eius et animi quos velit et.\",
    \"comment\": \"architecto\",
    \"schedule_from\": \"2026-01-27 14:56:26\",
    \"schedule_to\": \"2026-01-27 14:56:26\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/sales-leads/1/activities"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "type": "task",
    "title": "architecto",
    "description": "Eius et animi quos velit et.",
    "comment": "architecto",
    "schedule_from": "2026-01-27 14:56:26",
    "schedule_to": "2026-01-27 14:56:26"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-sales-leads--id--activities">
</span>
<span id="execution-results-POSTapi-sales-leads--id--activities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-sales-leads--id--activities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-sales-leads--id--activities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-sales-leads--id--activities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-sales-leads--id--activities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-sales-leads--id--activities" data-method="POST"
      data-path="api/sales-leads/{id}/activities"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-sales-leads--id--activities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-sales-leads--id--activities"
                    onclick="tryItOut('POSTapi-sales-leads--id--activities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-sales-leads--id--activities"
                    onclick="cancelTryOut('POSTapi-sales-leads--id--activities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-sales-leads--id--activities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/sales-leads/{id}/activities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-sales-leads--id--activities"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="1"
               data-component="url">
    <br>
<p>The ID of the sales lead. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="task"
               data-component="body">
    <br>
<p>Example: <code>task</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>task</code></li> <li><code>meeting</code></li> <li><code>call</code></li> <li><code>note</code></li> <li><code>file</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="architecto"
               data-component="body">
    <br>
<p>This field is required unless <code>type</code> is in <code>note</code> or <code>file</code>. Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="Eius et animi quos velit et."
               data-component="body">
    <br>
<p>Example: <code>Eius et animi quos velit et.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>comment</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="comment"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="POSTapi-sales-leads--id--activities"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the users table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>schedule_from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="schedule_from"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="2026-01-27 14:56:26"
               data-component="body">
    <br>
<p>This field is required unless <code>type</code> is in <code>note</code> or <code>file</code>. Must be a valid date in the format <code>Y-m-d H:i:s</code>. Example: <code>2026-01-27 14:56:26</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>schedule_to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="schedule_to"                data-endpoint="POSTapi-sales-leads--id--activities"
               value="2026-01-27 14:56:26"
               data-component="body">
    <br>
<p>This field is required unless <code>type</code> is in <code>note</code> or <code>file</code>. Must be a valid date in the format <code>Y-m-d H:i:s</code>. Example: <code>2026-01-27 14:56:26</code></p>
        </div>
        </form>

                    <h2 id="endpoints-PUTapi-webhooks-event">PUT api/webhooks/event</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-webhooks-event">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://crm.local.privatescan.nl/api/webhooks/event" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"entity_type\": \"forms\",
    \"id\": \"architecto\",
    \"action\": \"STATUS_UPDATE\",
    \"status\": \"architecto\",
    \"url\": \"http:\\/\\/www.bailey.biz\\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/webhooks/event"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "entity_type": "forms",
    "id": "architecto",
    "action": "STATUS_UPDATE",
    "status": "architecto",
    "url": "http:\/\/www.bailey.biz\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-webhooks-event">
</span>
<span id="execution-results-PUTapi-webhooks-event" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-webhooks-event"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-webhooks-event"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-webhooks-event" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-webhooks-event">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-webhooks-event" data-method="PUT"
      data-path="api/webhooks/event"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-webhooks-event', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-webhooks-event"
                    onclick="tryItOut('PUTapi-webhooks-event');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-webhooks-event"
                    onclick="cancelTryOut('PUTapi-webhooks-event');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-webhooks-event"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/webhooks/event</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="PUTapi-webhooks-event"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-webhooks-event"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-webhooks-event"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>entity_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="entity_type"                data-endpoint="PUTapi-webhooks-event"
               value="forms"
               data-component="body">
    <br>
<p>Example: <code>forms</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>forms</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-webhooks-event"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>action</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="action"                data-endpoint="PUTapi-webhooks-event"
               value="STATUS_UPDATE"
               data-component="body">
    <br>
<p>Example: <code>STATUS_UPDATE</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>STATUS_UPDATE</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="PUTapi-webhooks-event"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="url"                data-endpoint="PUTapi-webhooks-event"
               value="http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html"
               data-component="body">
    <br>
<p>Example: <code>http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-keycloak-webhooks">POST api/keycloak/webhooks</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-keycloak-webhooks">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/keycloak/webhooks" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/keycloak/webhooks"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-keycloak-webhooks">
</span>
<span id="execution-results-POSTapi-keycloak-webhooks" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-keycloak-webhooks"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-keycloak-webhooks"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-keycloak-webhooks" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-keycloak-webhooks">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-keycloak-webhooks" data-method="POST"
      data-path="api/keycloak/webhooks"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-keycloak-webhooks', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-keycloak-webhooks"
                    onclick="tryItOut('POSTapi-keycloak-webhooks');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-keycloak-webhooks"
                    onclick="cancelTryOut('POSTapi-keycloak-webhooks');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-keycloak-webhooks"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/keycloak/webhooks</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-keycloak-webhooks"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-keycloak-webhooks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-keycloak-webhooks"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-keycloak-persons--keycloakUserId-">Haal person id op op basis van Keycloak user id.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>GET /api/keycloak/persons/{keycloakUserId}</p>

<span id="example-requests-GETapi-keycloak-persons--keycloakUserId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/keycloak/persons/architecto" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/keycloak/persons/architecto"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-keycloak-persons--keycloakUserId-">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 55
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-keycloak-persons--keycloakUserId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-keycloak-persons--keycloakUserId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-keycloak-persons--keycloakUserId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-keycloak-persons--keycloakUserId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-keycloak-persons--keycloakUserId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-keycloak-persons--keycloakUserId-" data-method="GET"
      data-path="api/keycloak/persons/{keycloakUserId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-keycloak-persons--keycloakUserId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-keycloak-persons--keycloakUserId-"
                    onclick="tryItOut('GETapi-keycloak-persons--keycloakUserId-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-keycloak-persons--keycloakUserId-"
                    onclick="cancelTryOut('GETapi-keycloak-persons--keycloakUserId-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-keycloak-persons--keycloakUserId-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/keycloak/persons/{keycloakUserId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-keycloak-persons--keycloakUserId-"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-keycloak-persons--keycloakUserId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-keycloak-persons--keycloakUserId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>keycloakUserId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="keycloakUserId"                data-endpoint="GETapi-keycloak-persons--keycloakUserId-"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-patient--id--messages">Get all patient messages for a person, grouped by thread.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-patient--id--messages">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/patient/architecto/messages" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/patient/architecto/messages"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patient--id--messages">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 54
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patient--id--messages" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patient--id--messages"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patient--id--messages"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patient--id--messages" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patient--id--messages">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patient--id--messages" data-method="GET"
      data-path="api/patient/{id}/messages"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patient--id--messages', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patient--id--messages"
                    onclick="tryItOut('GETapi-patient--id--messages');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patient--id--messages"
                    onclick="cancelTryOut('GETapi-patient--id--messages');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patient--id--messages"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patient/{id}/messages</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-patient--id--messages"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patient--id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patient--id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-patient--id--messages"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the patient. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-patient--id--messages">Store a new patient message or reply.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-patient--id--messages">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "https://crm.local.privatescan.nl/api/patient/architecto/messages" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"body\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/patient/architecto/messages"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "body": "architecto"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-patient--id--messages">
</span>
<span id="execution-results-POSTapi-patient--id--messages" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-patient--id--messages"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-patient--id--messages"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-patient--id--messages" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-patient--id--messages">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-patient--id--messages" data-method="POST"
      data-path="api/patient/{id}/messages"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-patient--id--messages', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-patient--id--messages"
                    onclick="tryItOut('POSTapi-patient--id--messages');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-patient--id--messages"
                    onclick="cancelTryOut('POSTapi-patient--id--messages');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-patient--id--messages"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/patient/{id}/messages</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="POSTapi-patient--id--messages"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-patient--id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-patient--id--messages"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="POSTapi-patient--id--messages"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the patient. Example: <code>architecto</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>body</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="body"                data-endpoint="POSTapi-patient--id--messages"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-patient--id--activities-unread-count">Get the count of unread messages for a specific person.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-patient--id--activities-unread-count">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/patient/architecto/activities/unread/count" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/patient/architecto/activities/unread/count"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patient--id--activities-unread-count">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
x-ratelimit-limit: 60
x-ratelimit-remaining: 53
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid API key&quot;,
    &quot;message&quot;: &quot;The provided API key is not valid&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patient--id--activities-unread-count" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patient--id--activities-unread-count"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patient--id--activities-unread-count"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patient--id--activities-unread-count" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patient--id--activities-unread-count">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patient--id--activities-unread-count" data-method="GET"
      data-path="api/patient/{id}/activities/unread/count"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patient--id--activities-unread-count', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patient--id--activities-unread-count"
                    onclick="tryItOut('GETapi-patient--id--activities-unread-count');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patient--id--activities-unread-count"
                    onclick="cancelTryOut('GETapi-patient--id--activities-unread-count');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patient--id--activities-unread-count"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patient/{id}/activities/unread/count</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-patient--id--activities-unread-count"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patient--id--activities-unread-count"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patient--id--activities-unread-count"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-patient--id--activities-unread-count"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the patient. Example: <code>architecto</code></p>
            </div>
                    </form>

                <h1 id="patient-appointments">Patient appointments</h1>



                                <h2 id="patient-appointments-GETapi-patient--id--appointments">Get appointments for a patient (derived from Orders).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-patient--id--appointments">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "https://crm.local.privatescan.nl/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/appointments?filter=future" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/appointments"
);

const params = {
    "filter": "future",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-patient--id--appointments">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;order-123&quot;,
            &quot;patient_id&quot;: &quot;1&quot;,
            &quot;practitioner_id&quot;: null,
            &quot;clinic_id&quot;: null,
            &quot;clinic_label&quot;: null,
            &quot;start_at&quot;: &quot;2026-01-27T10:00:00+01:00&quot;,
            &quot;end_at&quot;: null,
            &quot;timezone&quot;: &quot;Europe/Amsterdam&quot;,
            &quot;is_remote&quot;: false,
            &quot;remote_url&quot;: null,
            &quot;created_at&quot;: &quot;2026-01-20T09:00:00+01:00&quot;,
            &quot;updated_at&quot;: &quot;2026-01-20T09:00:00+01:00&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Success (empty)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: []
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Patient not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Not Found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-patient--id--appointments" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-patient--id--appointments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-patient--id--appointments"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-patient--id--appointments" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-patient--id--appointments">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-patient--id--appointments" data-method="GET"
      data-path="api/patient/{id}/appointments"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-patient--id--appointments', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-patient--id--appointments"
                    onclick="tryItOut('GETapi-patient--id--appointments');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-patient--id--appointments"
                    onclick="cancelTryOut('GETapi-patient--id--appointments');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-patient--id--appointments"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/patient/{id}/appointments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="GETapi-patient--id--appointments"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-patient--id--appointments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-patient--id--appointments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-patient--id--appointments"
               value="3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d"
               data-component="url">
    <br>
<p>The Keycloak user ID of the patient. Example: <code>3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>filter</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="filter"                data-endpoint="GETapi-patient--id--appointments"
               value="future"
               data-component="query">
    <br>
<p>Filter appointments. Allowed values: future, past. Example: <code>future</code></p>
            </div>
                </form>

                <h1 id="patient-messages">Patient messages</h1>



                                <h2 id="patient-messages-PUTapi-patient--id--messages-mark_as_read">Mark all messages as read by patient (not employee)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-patient--id--messages-mark_as_read">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "https://crm.local.privatescan.nl/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/messages/mark_as_read" \
    --header "X-API-KEY: {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "https://crm.local.privatescan.nl/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/messages/mark_as_read"
);

const headers = {
    "X-API-KEY": "{YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PUT",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-patient--id--messages-mark_as_read">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Messages marked as read.&quot;,
    &quot;data&quot;: {
        &quot;marked_count&quot;: 3
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Patient not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Not Found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-patient--id--messages-mark_as_read" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-patient--id--messages-mark_as_read"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-patient--id--messages-mark_as_read"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-patient--id--messages-mark_as_read" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-patient--id--messages-mark_as_read">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-patient--id--messages-mark_as_read" data-method="PUT"
      data-path="api/patient/{id}/messages/mark_as_read"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-patient--id--messages-mark_as_read', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-patient--id--messages-mark_as_read"
                    onclick="tryItOut('PUTapi-patient--id--messages-mark_as_read');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-patient--id--messages-mark_as_read"
                    onclick="cancelTryOut('PUTapi-patient--id--messages-mark_as_read');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-patient--id--messages-mark_as_read"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/patient/{id}/messages/mark_as_read</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY" class="auth-value"               data-endpoint="PUTapi-patient--id--messages-mark_as_read"
               value="{YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-patient--id--messages-mark_as_read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-patient--id--messages-mark_as_read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-patient--id--messages-mark_as_read"
               value="3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d"
               data-component="url">
    <br>
<p>The Keycloak user ID of the patient. Example: <code>3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d</code></p>
            </div>
                    </form>




    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
