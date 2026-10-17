#!/usr/bin/env bash
#
# One-command server setup for Athari. Run as root on a fresh Ubuntu 24.04 VPS:
#
#   curl -fsSL https://raw.githubusercontent.com/bmitigoa/athari/main/deploy/bootstrap.sh | sudo bash
#
# What it does: updates the system, installs Docker, turns on the firewall
# (SSH, HTTP, HTTPS only), automatic security updates and a 2 GB swap file,
# downloads Athari from GitHub into /opt/athari/app, then runs install.sh.
# Piped into bash, the questions below are read from the terminal.
#
set -euo pipefail

REPO="${ATHARI_REPO:-https://github.com/bmitigoa/athari.git}"
REF="${ATHARI_REF:-main}"
BASE="${ATHARI_BASE:-/opt/athari}"

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
[ "$(id -u)" = "0" ] || { echo "Run this as root (sudo)." >&2; exit 1; }
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || echo "Warning: written for Ubuntu 24.04; this is ${PRETTY_NAME:-unknown}."

say "Updating the system"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y ca-certificates curl git ufw unattended-upgrades fail2ban

say "Installing Docker (official packages)"
if ! command -v docker >/dev/null; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
    >/etc/apt/sources.list.d/docker.list
  apt-get update -y
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

say "Firewall: only SSH, HTTP and HTTPS are open"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

# Oracle Cloud Ubuntu images ship with iptables rules that reject everything
# except SSH. Open the web ports there too, before the REJECT rule, and save.
if [ -f /etc/iptables/rules.v4 ]; then
  say "Oracle-style iptables rules found: opening 80 and 443"
  for rule in "tcp 80" "tcp 443" "udp 443"; do
    set -- $rule
    if ! iptables -C INPUT -p "$1" --dport "$2" -j ACCEPT 2>/dev/null; then
      pos="$(iptables -L INPUT --line-numbers -n | awk '/REJECT/{print $1; exit}')"
      iptables -I INPUT "${pos:-1}" -p "$1" --dport "$2" -j ACCEPT
    fi
  done
  if command -v netfilter-persistent >/dev/null; then
    netfilter-persistent save
  else
    iptables-save >/etc/iptables/rules.v4
  fi
fi

say "Automatic security updates and brute-force protection"
dpkg-reconfigure -f noninteractive unattended-upgrades
systemctl enable --now fail2ban

if [ ! -f /swapfile ] && [ "$(free -m | awk '/^Mem:/{print $2}')" -lt 3000 ]; then
  say "Adding a 2 GB swap file (this server has little memory)"
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >>/etc/fstab
fi

say "Downloading Athari ($REF)"
mkdir -p "$BASE"
if [ -d "$BASE/app/.git" ]; then
  git -C "$BASE/app" fetch --tags origin
  git -C "$BASE/app" checkout "$REF"
  git -C "$BASE/app" pull --ff-only origin "$REF" || true
else
  git clone "$REPO" "$BASE/app"
  git -C "$BASE/app" checkout "$REF"
fi

say "Running the installer"
cd "$BASE/app"
# When this script is piped from curl, stdin is the script. Give install.sh the terminal.
if [ ! -t 0 ] && [ -r /dev/tty ]; then
  exec bash deploy/install.sh </dev/tty
fi
exec bash deploy/install.sh
