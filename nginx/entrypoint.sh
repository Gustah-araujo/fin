#!/bin/sh
set -e

envsubst '${APP_DOMAIN}' < /etc/nginx/conf.d/app.conf.template > /etc/nginx/conf.d/default.conf
exec nginx -g 'daemon off;'
