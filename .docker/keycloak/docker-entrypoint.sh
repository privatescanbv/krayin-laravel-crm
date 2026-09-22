#!/bin/sh
set -e

if [ -d /opt/keycloak/themes-src ]; then
    cp -r /opt/keycloak/themes-src/. /opt/keycloak/themes/
fi

sed -i "s|\${env.FORMS_FRONTEND_URL}|${FORMS_FRONTEND_URL:-}|g" \
    /opt/keycloak/themes/privatescan/login/theme.properties

# Impersonation uses requested_subject (legacy token-exchange:v1).
# Keycloak 26.2+ enables Standard TE by default, which rejects requested_subject.
/opt/keycloak/bin/kc.sh build --features=token-exchange:v1 --features-disabled=token-exchange-standard
exec /opt/keycloak/bin/kc.sh start --optimized
