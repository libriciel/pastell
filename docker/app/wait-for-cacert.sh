#!/bin/bash

until test -f /etc/ssl/certs/ca-certificates.crt; do
  echo "$(date +'%Y-%m-%d %H:%M:%S') Certificates are unavailable - waiting"
    sleep 1
done

echo "$(date +'%Y-%m-%d %H:%M:%S') Certificates are available"