#!/usr/bin/env bash
# deploy/systemd/install_forex_robot_service.sh — FASE S18H
#
# Install smc-forex-robot systemd unit.
# Creates a symlink without spaces to avoid ExecStart/WorkingDirectory breakage.
#
# Usage:
#   bash deploy/systemd/install_forex_robot_service.sh
#   bash deploy/systemd/install_forex_robot_service.sh --start --enable
#   bash deploy/systemd/install_forex_robot_service.sh --user bimaq
#   bash deploy/systemd/install_forex_robot_service.sh --project-link /home/bimaq/projetos/smc_trader_system
set -euo pipefail

USER="${USER:-bimaq}"
PROJECT_LINK=""
START_SERVICE=false
ENABLE_SERVICE=false

die() { echo "ERROR: $*" >&2; exit 1; }
info() { echo "[INFO] $*"; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --start) START_SERVICE=true; shift ;;
        --enable) ENABLE_SERVICE=true; shift ;;
        --user) USER="$2"; shift 2 ;;
        --project-link) PROJECT_LINK="$2"; shift 2 ;;
        *) die "Unknown flag: $1" ;;
    esac
done

# --- detect project root ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
info "Project root: $PROJECT_ROOT"

# --- project link (symlink sem espacos) ---
if [[ -z "$PROJECT_LINK" ]]; then
    PROJECT_LINK="/home/$USER/projetos/smc_trader_system"
fi

if [[ -L "$PROJECT_LINK" ]]; then
    CURRENT_TARGET="$(readlink -f "$PROJECT_LINK")"
    if [[ "$CURRENT_TARGET" != "$PROJECT_ROOT" ]]; then
        die "Symlink $PROJECT_LINK exists but points to $CURRENT_TARGET, expected $PROJECT_ROOT. Remove it first or use --project-link."
    fi
    info "Symlink already exists and points correctly: $PROJECT_LINK -> $PROJECT_ROOT"
elif [[ -e "$PROJECT_LINK" ]]; then
    die "$PROJECT_LINK exists and is not a symlink. Remove it first."
else
    ln -s "$PROJECT_ROOT" "$PROJECT_LINK"
    info "Created symlink: $PROJECT_LINK -> $PROJECT_ROOT"
fi

# --- validate paths ---
PYTHON_BIN="$PROJECT_LINK/venv/bin/python"
FOREX_TOOL="$PROJECT_LINK/run_forex.py"
TMP_RUNTIME="$PROJECT_LINK/tmp_runtime"

if [[ ! -x "$PYTHON_BIN" ]]; then
    die "Python not found at $PYTHON_BIN"
fi
if [[ ! -f "$FOREX_TOOL" ]]; then
    die "Forex robot not found at $FOREX_TOOL"
fi
if [[ ! -d "$TMP_RUNTIME" ]]; then
    die "tmp_runtime not found at $TMP_RUNTIME"
fi
info "Python: $PYTHON_BIN"
info "Forex robot: $FOREX_TOOL"
info "tmp_runtime: $TMP_RUNTIME"

# --- generate unit file ---
UNIT_DIR="/etc/systemd/system"
FOREX_UNIT="$UNIT_DIR/smc-forex-robot.service"

info "Generating $FOREX_UNIT"
sudo tee "$FOREX_UNIT" > /dev/null <<UNIT
[Unit]
Description=SMC Trader Forex Robot
After=network.target smc-mt5linux-fx.service mysql.service
Wants=smc-mt5linux-fx.service mysql.service

[Service]
Type=simple
User=$USER
WorkingDirectory=$PROJECT_LINK
ExecStart=$PYTHON_BIN run_forex.py
Restart=always
RestartSec=15
Environment=PYTHONUNBUFFERED=1
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
UNIT

# --- daemon-reload ---
sudo systemctl daemon-reload
info "daemon-reload done"

# --- enable ---
if $ENABLE_SERVICE; then
    sudo systemctl enable smc-forex-robot
    info "Service enabled"
fi

# --- start ---
if $START_SERVICE; then
    sudo systemctl start smc-forex-robot
    info "Service started"
fi

echo ""
echo "Installation complete."
echo "  Forex unit: $FOREX_UNIT"
echo "  Symlink: $PROJECT_LINK -> $PROJECT_ROOT"
echo ""
echo "Check status:"
echo "  sudo systemctl status smc-forex-robot --no-pager"
echo ""
echo "View logs:"
echo "  sudo journalctl -u smc-forex-robot -n 100 --no-pager"
echo ""
echo "Validate:"
echo "  cat $TMP_RUNTIME/heartbeat_forex.json"
echo "  curl -s http://127.0.0.1:8008/api/health/collectors?refresh=true | python3 -m json.tool"
