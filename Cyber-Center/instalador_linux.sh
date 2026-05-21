#!/bin/bash
# INSTALADOR AUTOMÁTICO DEL AGENTE

# 1. Crear la carpeta oculta en el sistema
sudo mkdir -p /opt/CyberCenter

# 2. Mover el script de control a su lugar seguro
# (Asume que control_cyber.sh está en la misma carpeta que este instalador)
sudo cp control_cyber.sh /opt/CyberCenter/
sudo chmod +x /opt/CyberCenter/control_cyber.sh

# 3. Crear la carpeta de inicio automático para el usuario "estudiante"
# (Cambia "estudiante" por el nombre real del usuario si es otro)
USUARIO_CYBER="estudiante"
RUTA_AUTOSTART="/home/$USUARIO_CYBER/.config/autostart"
mkdir -p "$RUTA_AUTOSTART"

# 4. Crear el acceso directo invisible
cat <<EOF > "$RUTA_AUTOSTART"/cyberagent.desktop
[Desktop Entry]
Type=Application
Exec=/opt/CyberCenter/control_cyber.sh
Hidden=false
NoDisplay=true
X-GNOME-Autostart-enabled=true
Name=CyberAgent
Comment=Agente de control de sesión
EOF

# 5. Ajustar permisos finales
chown -R $USUARIO_CYBER:$USUARIO_CYBER "$RUTA_AUTOSTART"

echo "¡Instalación completada con éxito!"
