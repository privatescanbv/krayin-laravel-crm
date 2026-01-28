#!/bin/bash
# quick update crm, without updating env files etc
docker compose up -d --pull always crm
docker compose up -d --pull always forms
