#!/usr/bin/env bash
# deploy/systemd/install_opportunity_scanner_services.sh — FASE S17D
#
# Install smc-opportunity-scanner and smc-opportunity-notifier systemd units.
# Creates a symlink without spaces to avoid ExecStart/WorkingDirectory breakage.
#
# Usage:
#   bash deploy/systemd/install_opportunity_scanner_services.sh
#   bash deploy/systemd/install_opportunity_scanner_services.sh --start --enable
#   bash deploy/systemd/install_opportunity_scanner_services.sh --project-link /home/bimaq/projetos/smc_trader_system
set -euo pipefail

USER="${USER:-bimaq}"
PROJECT_LINK=""
START_SERVICES=false
ENABLE_SERVICES=false

die() { echo "ERROR: $*" >&2; exit 1; }
info() { echo "[INFO] $*"; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --start) START_SERVICES=true; shift ;;
        --enable) ENABLE_SERVICES=true; shift ;;
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

# --- validate venv and scanner tool ---
PYTHON_BIN="$PROJECT_LINK/venv/bin/python"
SCANNER_TOOL="$PROJECT_LINK/tools/run_opportunity_scanner.py"

if [[ ! -x "$PYTHON_BIN" ]]; then
    die "Python not found at $PYTHON_BIN"
fi
if [[ ! -f "$SCANNER_TOOL" ]]; then
    die "Scanner tool not found at $SCANNER_TOOL"
fi
info "Python: $PYTHON_BIN"
info "Scanner: $SCANNER_TOOL"

# --- generate unit files ---
UNIT_DIR="/etc/systemd/system"
SCANNER_UNIT="$UNIT_DIR/smc-opportunity-scanner.service"
NOTIFIER_UNIT="$UNIT_DIR/smc-opportunity-notifier.service"

info "Generating $SCANNER_UNIT"
sudo tee "$SCANNER_UNIT" > /dev/null <<UNIT
[Unit]
Description=SMC Trader Opportunity Scanner
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$PROJECT_LINK
ExecStart=$PYTHON_BIN tools/run_opportunity_scanner.py --loop --apply --heartbeat
Restart=always
RestartSec=10
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
UNIT

info "Generating $NOTIFIER_UNIT"
sudo tee "$NOTIFIER_UNIT" > /dev/null <<UNIT
[Unit]
Description=SMC Trader Opportunity Scanner Notifier
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$PROJECT_LINK
ExecStart=$PYTHON_BIN tools/run_opportunity_scanner.py --process-outbox --loop --poll-interval 5
Restart=always
RestartSec=10
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
UNIT

# --- daemon-reload ---
sudo systemctl daemon-reload
info "daemon-reload done"

# --- enable ---
if $ENABLE_SERVICES; then
    sudo systemctl enable smc-opportunity-scanner smc-opportunity-notifier
    info "Services enabled"
fi

# --- start ---
if $START_SERVICES; then
    sudo systemctl start smc-opportunity-scanner smc-opportunity-notifier
    info "Services started"
fi

echo ""
echo "Installation complete."
echo "  Scanner unit: $SCANNER_UNIT"
echo "  Notifier unit: $NOTIFIER_UNIT"
echo "  Symlink: $PROJECT_LINK -> $PROJECT_ROOT"
echo ""
echo "Check status:"
echo "  sudo systemctl status smc-opportunity-scanner smc-opportunity-notifier"
echo ""
echo "View logs:"
echo "  sudo journalctl -u smc-opportunity-scanner -f"
echo "  sudo journalctl -u smc-opportunity-notifier -f"
