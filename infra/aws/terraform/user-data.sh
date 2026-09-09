#!/bin/bash
set -euo pipefail
dnf update -y
dnf install -y docker git nginx certbot python3-certbot-nginx awscli2 amazon-cloudwatch-agent
systemctl enable --now docker nginx amazon-cloudwatch-agent
usermod -aG docker ec2-user
mkdir -p /opt/movi
chown ec2-user:ec2-user /opt/movi
