#!/usr/bin/env bash
# deploy/systemd/install_forward_shadow_runner.sh — FASE S19D
#
# Install smc-study-forward-shadow.service + timer para gerar planos operacionais.
# Shadow-only. Nao executa ordens.
#
# Usage:
#   sudo bash deploy/systemd/install_forward_shadow_runner.sh --start --enable
set -euo pipefail

USER="${USER:-bimaq}"
PROJECT_LINK="/home/$USER/projetos/smc_trader_system"
START_SERVICE=false
ENABLE_SERVICE=false

die() { echo "ERROR: $*" >&2; exit 1; }
info() { echo "[INFO] $*"; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --start) START_SERVICE=true; shift ;;
        --enable) ENABLE_SERVICE=true; shift ;;
        *) die "Unknown flag: $1" ;;
    esac
done

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
info "Project root: $PROJECT_ROOT"

UNIT_DIR="/etc/systemd/system"

# --- service unit ---
info "Installing smc-study-forward-shadow.service"
cp "$SCRIPT_DIR/smc-study-forward-shadow.service" "$UNIT_DIR/smc-study-forward-shadow.service"
sed -i "s|/home/bimaq/projetos/smc_trader_system|$PROJECT_LINK|g" "$UNIT_DIR/smc-study-forward-shadow.service"
sed -i "s|User=bimaq|User=$USER|g" "$UNIT_DIR/smc-study-forward-shadow.service"

# --- timer unit ---
info "Installing smc-study-forward-shadow.timer"
cp "$SCRIPT_DIR/smc-study-forward-shadow.timer" "$UNIT_DIR/smc-study-forward-shadow.timer"

systemctl daemon-reload
info "daemon-reload done"

if $ENABLE_SERVICE; then
    systemctl enable smc-study-forward-shadow.timer
    info "Timer enabled"
fi

if $START_SERVICE; then
    systemctl start smc-study-forward-shadow.timer
    info "Timer started"
fi

echo ""
echo "Installation complete."
echo "  Service: $UNIT_DIR/smc-study-forward-shadow.service"
echo "  Timer:   $UNIT_DIR/smc-study-forward-shadow.timer"
echo ""
echo "Manual run:"
echo "  sudo systemctl start smc-study-forward-shadow.service"
echo ""
echo "Check status:"
echo "  sudo systemctl status smc-study-forward-shadow.timer"
echo "  sudo systemctl status smc-study-forward-shadow.service"
echo ""
echo "View logs:"
echo "  sudo journalctl -u smc-study-forward-shadow.service -f"
