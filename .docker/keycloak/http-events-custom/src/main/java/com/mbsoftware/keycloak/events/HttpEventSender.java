package com.mbsoftware.keycloak.events;

import org.keycloak.events.Event;
import org.keycloak.events.admin.AdminEvent;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.logging.Logger;

public class HttpEventSender {

    private static final Logger LOG = Logger.getLogger(HttpEventSender.class.getName());

    private final String endpoint;
    private final String secret;
    private final String maskedSecret;

    public HttpEventSender(String endpoint, String secret) {
        this.endpoint = endpoint;
        this.secret = secret;
        this.maskedSecret = secret == null ? "" : "****" + secret.substring(Math.max(0, secret.length() - 4));
    }

    /* ---------------------------------------------------------
     * PUBLIC METHODS
     * --------------------------------------------------------- */
    public void send(Event e) {
        String json = json(
                entry("type", e.getType()),
                entry("userId", e.getUserId()),
                entry("clientId", e.getClientId()),
                entry("realmId", e.getRealmId()),
                entry("ipAddress", e.getIpAddress())
        );

        LOG.info(String.format("KC EVENT: %s -> POST %s", e.getType(), endpoint));
        sendJson(json);
    }

    public void sendAdminEvent(AdminEvent e) {
        String userId = e.getAuthDetails() != null ? safe(e.getAuthDetails().getUserId()) : "";
        String ip = e.getAuthDetails() != null ? safe(e.getAuthDetails().getIpAddress()) : "";

        String json = json(
                entry("type", "ADMIN_EVENT"),
                entry("operationType", e.getOperationType()),
                entry("resourceType", e.getResourceType()),
                entry("userId", userId),
                entry("realmId", e.getRealmId()),
                entry("ipAddress", ip),
                entry("resourcePath", e.getResourcePath())
        );

        LOG.info(String.format("KC ADMIN EVENT: %s/%s user=%s -> POST %s",
                e.getOperationType(), e.getResourceType(), userId, endpoint));

        sendJson(json);
    }

    /* ---------------------------------------------------------
     * SEND HELPER
     * --------------------------------------------------------- */
    private void sendJson(String json) {
        try {
            HttpURLConnection conn = (HttpURLConnection) new URL(endpoint).openConnection();

            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("X-API-KEY", secret);
            conn.setDoOutput(true);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(json.getBytes());
            }

            int code = conn.getResponseCode();
            LOG.info(String.format("KC EVENT SENT: HTTP %d endpoint=%s secret=%s", code, endpoint, maskedSecret));

            conn.disconnect();
        } catch (Exception ex) {
            LOG.severe("KC HTTP Event Sender ERROR: " + ex.getMessage());
        }
    }

    /* ---------------------------------------------------------
     * JSON HELPERS
     * --------------------------------------------------------- */
    private String safe(Object v) {
        return v == null ? "" : v.toString();
    }

    private String entry(String key, Object value) {
        return "\"" + key + "\":\"" + safe(value) + "\"";
    }

    private String json(String... entries) {
        return "{" + String.join(",", entries) + "}";
    }
}

