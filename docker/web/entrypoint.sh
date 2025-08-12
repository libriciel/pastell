#!/bin/sh

set -e

/bin/sh /usr/local/bin/certbot-acme.sh --standalone
/bin/sh /usr/local/bin/self-signed-certificates.sh

exec "$@"