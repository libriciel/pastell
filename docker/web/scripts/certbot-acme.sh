#!/bin/sh

ACME_LOGS_DIR=/data/log
ACME_WORK_DIR=/data/run
ACME_WEBROOT_PATH=/acme-challenge

STANDALONE=false

for arg in "$@"; do
  if [ "$arg" = "--standalone" ]; then
    STANDALONE=true
  fi
done

should_renew_cert() {
  cert_path="/etc/letsencrypt/live/$1/fullchain.pem"
  renew_before_days="$2"

  if [ ! -f "$cert_path" ]; then
    echo "Certificate for $1 does not exist. Will request a new one."
    return 0
  fi

  expiration_date=$(openssl x509 -enddate -noout -in "$cert_path" 2>/dev/null | cut -d= -f2)
  if [ -z "$expiration_date" ]; then
      echo "Unable to read expiration date for $1. Will attempt to renew."
      return 0
  fi

  expiration_ts=$(date -d "$expiration_date" +%s)
  now_ts=$(date +%s)
  renew_before_ts=$((now_ts + renew_before_days * 86400))
  days_left=$(( (expiration_ts - now_ts) / 86400 ))
  echo "Certificate $1 expires on: $expiration_date ($days_left days left)"
  if [ "$expiration_ts" -lt "$renew_before_ts" ]; then
    echo "Expiring soon. Will renew."
    return 0
  fi

  echo "Still valid. Skipping renewal."
  return 1
}

request_new_cert() {
  name="$1"
  domain="$2"
  email="$3"
  server="$4"
  key_type="$5"
  eab_kid="$6"
  eab_hmac="$7"
  domains_args="$8"

  echo "Requesting new certificate for $domain..."

  # Base arguments
  args="certonly \
    --non-interactive \
    --agree-tos \
    --logs-dir $ACME_LOGS_DIR \
    --work-dir $ACME_WORK_DIR \
    --key-type $key_type \
    --server $server \
    -m $email \
    $domains_args"

  # Add validation method based on mode
  if [ "$STANDALONE" = "true" ]; then
    echo "Using standalone mode"
    args="$args --standalone --http-01-port 80"
  else
    echo "Using webroot mode"
    args="$args --webroot --webroot-path $ACME_WEBROOT_PATH --http-01-port 80"
  fi

  if [ -n "$eab_kid" ] && [ -n "$eab_hmac" ]; then
    args="$args --eab-kid $eab_kid --eab-hmac-key $eab_hmac"
  fi

  if ! certbot $args; then
    echo "Failed to obtain certificate for $domain"
    return 1
  fi
  return 0
}

renew_existing_cert() {
  domain="$1"
  echo "Attempting to renew certificate for $domain..."

  # Base renewal arguments
  args="renew \
  --logs-dir $ACME_LOGS_DIR \
  --work-dir $ACME_WORK_DIR \
  --cert-name $domain"

  # Add validation method based on mode
  if [ "$STANDALONE" = "true" ]; then
    echo "Using standalone mode for renewal"
    args="$args --standalone"
  else
    echo "Using webroot mode for renewal"
    args="$args --webroot --webroot-path $ACME_WEBROOT_PATH"
  fi

  if ! certbot $args; then
    echo "Failed to renew certificate for $domain"
    return 1
  fi
  return 0
}

copy_cert_files() {
  domain="$1"
  dest_path="$2"

  if [ -z "$dest_path" ]; then
    echo "No destination path (DEST) specified for $domain"
    return
  fi

  mkdir -p "$dest_path"
  cp "/etc/letsencrypt/live/$domain/fullchain.pem" "$dest_path/"
  cp "/etc/letsencrypt/live/$domain/privkey.pem" "$dest_path/"
  chmod 600 "$dest_path/fullchain.pem" "$dest_path/privkey.pem"
  echo "Certificate files for $domain copied to $dest_path"
}

process_cert() {
  name="$1"
  prefix="ACME_${name}_"
  eval enabled=\$"${prefix}ENABLED"

    if [ "$enabled" != "true" ]; then
      echo "[INFO] ACME certificate '$name' is disabled, skipping."
      return
    fi

    eval server=\$"${prefix}SERVER"
    eval email=\$"${prefix}EMAIL"
    eval domain=\$"${prefix}DOMAIN"
    eval additional_domains=\$"${prefix}ADDITIONAL_DOMAINS"
    eval renew_days=\$"${prefix}RENEW_BEFORE_DAYS"
    eval key_type=\$"${prefix}KEY_TYPE"
    eval eab_kid=\$"${prefix}EAB_KID"
    eval eab_hmac=\$"${prefix}EAB_HMAC_KEY"
    eval dest_path=\$"${prefix}DEST"

    renew_days="${renew_days:-30}"
    key_type="${key_type:-rsa}"
    server="${server:-https://acme-staging-v02.api.letsencrypt.org/directory}"

    if [ -z "$domain" ]; then
      echo "[WARN] Missing domain for $name"
      return
    fi

    if [ -z "$email" ]; then
      echo "[WARN] Missing email for $name"
      return
    fi

    domains_args="-d $domain"
      for d in $additional_domains; do
        domains_args="$domains_args -d $d"
      done

  if should_renew_cert "$domain" "$renew_days"; then
      if [ -f "/etc/letsencrypt/live/$domain/fullchain.pem" ]; then
        renew_existing_cert "$domain" || return
      else
        request_new_cert "$name" "$domain" "$email" "$server" "$key_type" "$eab_kid" "$eab_hmac" "$domains_args" || return
      fi
    fi

  copy_cert_files "$domain" "$dest_path"

}

# Display mode information
if [ "$STANDALONE" = "true" ]; then
  echo "Running in STANDALONE mode - make sure no web server is running on port 80"
else
  echo "Running in WEBROOT mode - make sure web server is running and serving $ACME_WEBROOT_PATH"
fi

# Discover all ACME_<NAME>_ENABLED variables
for var in $(env | grep '^ACME_.*_ENABLED=' | cut -d= -f1); do
  printf '\n\n===================\n'
  name=$(echo "$var" | sed -E 's/^ACME_(.*)_ENABLED$/\1/')
  echo "Processing $name"
  printf '===================\n'
  process_cert "$name"
done
