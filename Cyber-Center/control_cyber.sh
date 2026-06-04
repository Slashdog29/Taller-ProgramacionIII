#!/bin/bash
# control_cyber.sh
# Agente en Bash para Linux que consulta el endpoint verificar_estado.php
# Requisitos: curl y jq instalados en el sistema

# ---------------------------------------------------------------
# CONFIGURAR: Cambia la variable SERVER_URL a la dirección de tu servidor
# Ejemplo: SERVER_URL="http://192.168.1.100/Cyber-Center/src"
# ---------------------------------------------------------------
SERVER_URL="http://localhost/Cyber-Center/src"

while true; do
    # Obtener respuesta JSON del servidor de forma silenciosa con un timeout de 10 segundos
    RESPONSE=$(curl -s --max-time 10 "$SERVER_URL/verificar_estado.php")
    
    if [ $? -ne 0 ]; then
        # Si el servidor no responde, esperar y reintentar
        sleep 30
        continue
    fi

    # Comprobar si la respuesta contiene la instrucción de bloqueo usando jq
    # (Se asegura de que sea un JSON válido y que el campo "accion" sea "bloquear")
    ACCION=$(echo "$RESPONSE" | jq -r '.accion' 2>/dev/null)

    if [ "$ACCION" = "bloquear" ]; then
        # loginctl terminate-session self es más preciso para el usuario actual
        echo "Instrucción de bloqueo recibida. Cerrando sesión..."
        loginctl terminate-session self || loginctl terminate-user "$USER"
    fi

    # Esperar 30 segundos antes de la siguiente consulta
    sleep 30
done
