FROM quay.io/keycloak/keycloak:24.0

USER root

COPY .docker/keycloak/themes /opt/keycloak/themes
COPY .docker/keycloak/providers /opt/keycloak/providers

RUN chown -R keycloak:keycloak /opt/keycloak/themes /opt/keycloak/providers

USER keycloak

ENTRYPOINT ["/opt/keycloak/bin/kc.sh"]
CMD ["start"]
