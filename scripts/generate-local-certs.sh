#!/bin/bash
# Genereert een eigen lokale CA + wildcard-certificaat voor *.local.privatescan.nl
# in ./certs, voor traefik (zie .docker/traefik_local/tls.yml).
#
# Waarom per ontwikkelaar: een certificaat is pas vertrouwd als de CA die het
# ondertekende in jouw trust store staat. Een gedeeld cert uit de repo is dus
# altijd "niet vertrouwd" voor iedereen behalve degene die het maakte. Daarom
# staan certs/*.pem in .gitignore en maakt iedereen zijn eigen set.
#
# Gebruik:
#   ./scripts/generate-local-certs.sh          # maakt CA (eenmalig) + leaf-cert
#   ./scripts/generate-local-certs.sh --force  # vervangt ook een bestaande CA
#
# Werkt met de LibreSSL die standaard op macOS staat; mkcert is niet nodig.
set -euo pipefail

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/certs"
CA_KEY="$CERT_DIR/localCA-key.pem"
CA_CRT="$CERT_DIR/localCA.pem"
LEAF_KEY="$CERT_DIR/local-key.pem"
LEAF_CRT="$CERT_DIR/local.pem"

# Apple vertrouwt leaf-certificaten tot 825 dagen; de CA mag langer.
CA_DAYS=3650
LEAF_DAYS=825

FORCE=0
[ "${1:-}" = "--force" ] && FORCE=1

mkdir -p "$CERT_DIR"

# CN mag maximaal 64 tekens zijn (ASN.1 PrintableString), dus afkappen.
WHO="$(whoami)@$(hostname -s)"
CA_CN="PrivateScan local dev CA ($WHO)"
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
O = PrivateScan local development
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

if [ ! -f "$CA_CRT" ] || [ ! -f "$CA_KEY" ] || [ "$FORCE" = "1" ]; then
    echo "🔑 Eigen lokale CA aanmaken…"
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

if [ "$NEW_CA" = "1" ]; then
    echo
    echo "👉 Vertrouw de CA eenmalig (vraagt om je wachtwoord):"
    echo "   sudo security add-trusted-cert -d -r trustRoot \\"
    echo "     -k /Library/Keychains/System.keychain \"$CA_CRT\""
    echo
    echo "   Firefox gebruikt zijn eigen trust store: zet daar"
    echo "   security.enterprise_roots.enabled op true in about:config."
fi

echo
echo "👉 Herstart traefik zodat het nieuwe cert geladen wordt:"
echo "   docker compose restart traefik"
