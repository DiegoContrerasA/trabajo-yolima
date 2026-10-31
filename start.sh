#!/bin/sh
# Flask solo escucha en localhost (no expuesto); PHP es la cara pública
cd /app/api
gunicorn -w 1 -b 127.0.0.1:5000 app:app &
# Espera a que Flask esté listo
sleep 2
exec php -S 0.0.0.0:${PORT:-7860} -t /app/web
