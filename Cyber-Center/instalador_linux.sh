#!/bin/bash
# INSTALADOR AUTOMÁTICO DEL AGENTE

# 1. Crear la carpeta oculta en el sistema
sudo mkdir -p /opt/CyberCenter

# 2. Mover el script de control a su lugar seguro
# (Asume que control_cyber.sh está en la misma carpeta que este instalador)
sudo cp control_cyber.sh /opt/CyberCenter/
sudo chmod +x /opt/CyberCenter/control_cyber.sh

# 4. Configurar inicio automático para el usuario actual
USUARIO_CYBER=$(whoami)
RUTA_AUTOSTART="/home/$USUARIO_CYBER/.config/autostart"
mkdir -p "$RUTA_AUTOSTART"

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

sudo chown -R $USUARIO_CYBER:$USUARIO_CYBER "$RUTA_AUTOSTART"

echo "¡Instalación de pruebas completada con éxito para el usuario $USUARIO_CYBER!"

echo "¡Instalación completada con éxito!"
