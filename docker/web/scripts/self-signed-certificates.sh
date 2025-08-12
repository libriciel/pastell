#!/bin/sh

CERT_FILENAME=fullchain.pem
KEY_FILENAME=privkey.pem
CERTIFICATE_DIRECTORY=/data/certificates
APP_CERTIFICATE_DIRECTORY=$CERTIFICATE_DIRECTORY/app
MAILSEC_CERTIFICATE_DIRECTORY=$CERTIFICATE_DIRECTORY/mailsec

cert_files_exist() {
  dir=$1
  if [ -f "$dir/$CERT_FILENAME" ] && [ -f "$dir/$KEY_FILENAME" ]; then
    return 0
  else
    return 1
  fi
}

generate_cert() {
  dir=$1
  name=$2
  mkdir -p "$dir"
  echo "Generating a new self-signed certificate for $name in $dir"

  # Create a temporary OpenSSL config with proper extensions
  CONFIG_FILE="$dir/openssl-server.cnf"
  cat > "$CONFIG_FILE" <<EOF
[req]
distinguished_name = req_distinguished_name
x509_extensions = v3_req
prompt = no

[req_distinguished_name]
CN = $name

[v3_req]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
EOF

  openssl req -x509 -nodes -days 365 \
    -newkey rsa:4096 \
    -keyout "$dir/$KEY_FILENAME" \
    -out "$dir/$CERT_FILENAME" \
    -config "$CONFIG_FILE" \
    -extensions v3_req

  rm -f "$CONFIG_FILE"

  chmod 400 "$dir/$KEY_FILENAME"
}

if cert_files_exist "$APP_CERTIFICATE_DIRECTORY"; then
  echo "Certificate and key found in $APP_CERTIFICATE_DIRECTORY"
else
  echo "Certificate or key missing in $APP_CERTIFICATE_DIRECTORY"
  generate_cert "$APP_CERTIFICATE_DIRECTORY" "$APP_HOST"
fi

if cert_files_exist "$MAILSEC_CERTIFICATE_DIRECTORY"; then
  echo "Certificate and key found in $MAILSEC_CERTIFICATE_DIRECTORY"
else
  echo "Certificate or key missing in $MAILSEC_CERTIFICATE_DIRECTORY"
  generate_cert "$MAILSEC_CERTIFICATE_DIRECTORY" "$MAILSEC_HOST"
fi
