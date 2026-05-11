#!/bin/sh
set -e

if [ -d /opt/keycloak/themes-src ]; then
    cp -r /opt/keycloak/themes-src/. /opt/keycloak/themes/
fi

sed -i "s|\${env.FORMS_FRONTEND_URL}|${FORMS_FRONTEND_URL:-}|g" \
    /opt/keycloak/themes/privatescan/login/theme.properties

/opt/keycloak/bin/kc.sh build --features=token-exchange
exec /opt/keycloak/bin/kc.sh start --optimized
