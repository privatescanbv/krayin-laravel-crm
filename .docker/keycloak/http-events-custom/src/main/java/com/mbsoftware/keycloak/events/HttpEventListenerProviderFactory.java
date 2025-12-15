package com.mbsoftware.keycloak.events;

import org.keycloak.events.EventListenerProvider;
import org.keycloak.events.EventListenerProviderFactory;
import org.keycloak.models.KeycloakSession;
import org.keycloak.models.KeycloakSessionFactory;

public class HttpEventListenerProviderFactory implements EventListenerProviderFactory {

    public static final String ID = "http-events";

    private final String endpoint = System.getenv("KC_HTTP_EVENTS_ENDPOINT");
    private final String secret   = System.getenv("KC_HTTP_EVENTS_SECRET");

   @Override
   public EventListenerProvider create(KeycloakSession session) {
//         String maskedSecret = secret == null ? null : (secret.length() <= 4 ? "****" : "****" + secret.substring(secret.length() - 4));
//         java.util.logging.Logger.getLogger(HttpEventListenerProviderFactory.class.getName())
//         .info(String.format("Creating HttpEventListenerProvider with endpoint=%s secret=%s", endpoint, maskedSecret));

        return new HttpEventListenerProvider(new HttpEventSender(endpoint, secret));
   }

    // IMPORTANT: Required by ProviderFactory interface in KC26.
    // Must exist, but we do not import Config.
    @Override
    public void init(org.keycloak.Config.Scope config) {
        // no-op (configuration via environment variables)
    }

    @Override
    public void postInit(KeycloakSessionFactory factory) {}

    @Override
    public void close() {}

    @Override
    public String getId() {
        return ID;
    }
}
