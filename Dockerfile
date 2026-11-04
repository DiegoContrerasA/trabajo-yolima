FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends python3 python3-pip python3-venv \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# La base de datos queda FUERA de la carpeta web
COPY index.php php.php auth.php login.php logout.php imprimir.php exportar.php ./web/
COPY app.py ./api/
COPY FORMULARIO.db ./data/FORMULARIO.db
COPY start.sh ./start.sh
RUN chmod +x start.sh && chmod -R a+rwX /app/data

ENV DB_PATH=/app/data/FORMULARIO.db
ENV PORT=7860
EXPOSE 7860
CMD ["/app/start.sh"]
