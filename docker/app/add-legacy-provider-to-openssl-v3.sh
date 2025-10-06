#!/bin/sh

set -ex

# see https://github.com/openssl/openssl/issues/13180

OPENSSL_CONF="/etc/ssl/openssl.cnf"

sed -i '/^default = default_sect/a legacy = legacy_sect' "$OPENSSL_CONF"
sed -i '/^\[default_sect\]/a activate = 1' "$OPENSSL_CONF"
printf '\n[legacy_sect]\nactivate = 1\n' >> "$OPENSSL_CONF"