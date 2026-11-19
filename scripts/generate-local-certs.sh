#!/bin/bash
# Genereert een wildcard-certificaat voor *.local.privatescan.nl in ./certs,
# voor traefik (.docker/traefik_local/tls.yml) en Vite (Admin vite.config.js).
#
# De CA staat buiten de repo in ~/.local-dev-ca en wordt gedeeld met de andere
# PrivateScan-projecten (o.a. clinic-portal, scripts/generate-local-cert.sh).
# Zo vertrouw je één CA en werken alle *.local.privatescan.nl-projecten.
#
# Waarom per ontwikkelaar: een certificaat is pas vertrouwd als de CA die het
# ondertekende in jouw trust store staat. Een gedeeld cert uit de repo is dus
# altijd "niet vertrouwd" voor iedereen behalve degene die het maakte. Daarom
# staat /certs/* in .gitignore en maakt iedereen zijn eigen set.
#
# Gebruik:
#   ./scripts/generate-local-certs.sh            # leaf-cert (CA alleen als die mist)
#   ./scripts/generate-local-certs.sh --new-ca    # vervangt OOK de gedeelde CA
#
# Werkt met de LibreSSL die standaard op macOS staat; mkcert is niet nodig.
set -euo pipefail

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/certs"
# Zelfde locatie en bestandsnamen als clinic-portal, zodat beide projecten
# dezelfde CA hergebruiken.
CA_DIR="${LOCAL_DEV_CA_DIR:-$HOME/.local-dev-ca}"
CA_KEY="$CA_DIR/rootCA-key.pem"
CA_CRT="$CA_DIR/rootCA.pem"
LEAF_KEY="$CERT_DIR/local-key.pem"
LEAF_CRT="$CERT_DIR/local.pem"

# Apple vertrouwt leaf-certificaten tot 825 dagen; de CA mag langer.
CA_DAYS=3650
LEAF_DAYS=825

NEW_CA_REQUESTED=0
if [ "${1:-}" = "--new-ca" ]; then
    NEW_CA_REQUESTED=1
    echo "⚠️  Je vervangt de gedeelde CA in ${LOCAL_DEV_CA_DIR:-$HOME/.local-dev-ca}."
    echo "   Certificaten van andere projecten (o.a. clinic-portal) worden daarmee"
    echo "   ongeldig en je moet de nieuwe CA opnieuw vertrouwen."
    printf "   Doorgaan? [y/N] "
    read -r answer
    [ "$answer" = "y" ] || { echo "Afgebroken."; exit 1; }
fi

mkdir -p "$CERT_DIR" "$CA_DIR"

# CN mag maximaal 64 tekens zijn (ASN.1 PrintableString), dus afkappen.
CA_CN="Local dev CA $(whoami)"
CA_CN="${CA_CN:0:64}"
CONF="$(mktemp -t privatescan-cert-conf)"
CSR="$(mktemp -t privatescan-cert-csr)"
trap 'rm -f "$CONF" "$CSR"' EXIT

# De CN staat per certificaat anders; CA en leaf mogen nooit dezelfde
# distinguished name krijgen, anders ziet een validator het leaf als
# self-signed. $ENV:: werkt niet in de LibreSSL van macOS, dus schrijven we
# de config per certificaat opnieuw met de CN er al in.
write_conf() {
    cat > "$CONF" <<EOF
[req]
distinguished_name = dn
prompt = no

[dn]
O = Local development
CN = $1

[ca_ext]
basicConstraints = critical,CA:TRUE,pathlen:0
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash

[leaf_ext]
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature,keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt

[alt]
DNS.1 = *.local.privatescan.nl
DNS.2 = local.privatescan.nl
DNS.3 = localhost
IP.1 = 127.0.0.1
EOF
}

if [ ! -f "$CA_CRT" ] || [ ! -f "$CA_KEY" ] || [ "$NEW_CA_REQUESTED" = "1" ]; then
    echo "🔑 Nieuwe gedeelde lokale CA in $CA_DIR"
    write_conf "$CA_CN"
    openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days "$CA_DAYS" \
        -keyout "$CA_KEY" -out "$CA_CRT" \
        -config "$CONF" -extensions ca_ext
    NEW_CA=1
else
    echo "🔑 Bestaande CA hergebruikt: $CA_CRT"
    NEW_CA=0
fi

echo "📄 Wildcard-certificaat aanmaken voor *.local.privatescan.nl…"
write_conf "*.local.privatescan.nl"
openssl req -new -nodes -newkey rsa:2048 -sha256 \
    -keyout "$LEAF_KEY" -out "$CSR" \
    -config "$CONF"

openssl x509 -req -in "$CSR" -CA "$CA_CRT" -CAkey "$CA_KEY" -CAcreateserial \
    -out "$LEAF_CRT" -days "$LEAF_DAYS" -sha256 \
    -extfile "$CONF" -extensions leaf_ext

chmod 600 "$CA_KEY" "$LEAF_KEY"
chmod 644 "$CA_CRT" "$LEAF_CRT"

echo
echo "✅ Aangemaakt:"
echo "   $CA_CRT   (CA — deze moet je vertrouwen)"
echo "   $LEAF_CRT (cert dat traefik serveert)"
openssl x509 -in "$LEAF_CRT" -noout -subject -dates

if ! security verify-cert -c "$LEAF_CRT" >/dev/null 2>&1; then
    echo
    echo "👉 Deze Mac vertrouwt de CA nog niet. Eenmalig toevoegen"
    echo "   (vraagt om je wachtwoord):"
    echo "   sudo security add-trusted-cert -d -r trustRoot \\"
    echo "     -k /Library/Keychains/System.keychain \"$CA_CRT\""
    echo
    echo "   Firefox gebruikt zijn eigen trust store: zet daar"
    echo "   security.enterprise_roots.enabled op true in about:config."
fi

echo
echo "👉 Herstart traefik zodat het nieuwe cert geladen wordt:"
echo "   docker compose restart traefik"
