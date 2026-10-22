FROM quay.io/keycloak/keycloak:26.6

USER root

COPY .docker/keycloak/themes /opt/keycloak/themes
COPY .docker/keycloak/docker-entrypoint.sh /opt/keycloak/docker-entrypoint.sh

RUN chown -R keycloak:keycloak /opt/keycloak/themes /opt/keycloak/docker-entrypoint.sh && \
    chmod +x /opt/keycloak/docker-entrypoint.sh

USER keycloak

ENTRYPOINT ["/bin/sh", "/opt/keycloak/docker-entrypoint.sh"]
