package com.mbsoftware.keycloak.events;

import org.keycloak.events.Event;
import org.keycloak.events.EventListenerProvider;
import org.keycloak.events.admin.AdminEvent;

public class HttpEventListenerProvider implements EventListenerProvider {

    private final HttpEventSender sender;

    public HttpEventListenerProvider(HttpEventSender sender) {
        this.sender = sender;
    }

    @Override
    public void onEvent(Event event) {
        sender.send(event);
    }

    @Override
    public void onEvent(AdminEvent adminEvent, boolean includeRepresentation) {
        sender.sendAdminEvent(adminEvent);
    }

    @Override
    public void close() {}
}
