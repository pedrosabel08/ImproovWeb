#!/bin/bash
# Slack connectivity monitor
# Upload to VPS and run with: bash /tmp/slack_monitor.sh &
# Log output: /tmp/slack_monitor.log

LOG=/tmp/slack_monitor.log
WEBHOOK_URL="https://hooks.slack.com/services/T0884Q7MPCL/B0884Q7MTE3/$(grep SLACK_WEBHOOK_MAIN_URL /home/improov/web/improov.com.br/public_html/flow/ImproovWeb/.env 2>/dev/null | cut -d'/' -f8)"
ITERATIONS=40
INTERVAL=30

# Lê token e webhook direto do .env do projeto
ENV_FILE="/home/improov/web/improov.com.br/public_html/flow/ImproovWeb/.env"

get_env() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | tr -d "'\""
}

SLACK_TOKEN=$(get_env SLACK_TOKEN)
SLACK_WEBHOOK=$(get_env SLACK_WEBHOOK_MAIN_URL)

echo "=== Slack Monitor iniciado $(date) ===" > "$LOG"
echo "ENV_FILE=$ENV_FILE" >> "$LOG"
echo "TOKEN_SET=$([ -n "$SLACK_TOKEN" ] && echo YES || echo NO)" >> "$LOG"
echo "WEBHOOK_SET=$([ -n "$SLACK_WEBHOOK" ] && echo YES || echo NO)" >> "$LOG"
echo "" >> "$LOG"

for i in $(seq 1 $ITERATIONS); do
    TS=$(date '+%Y-%m-%d %H:%M:%S')
    echo "--- Iteração $i / $ITERATIONS  $TS ---" >> "$LOG"

    # 1. DNS resolution
    DNS_RESULT=$(host -W 3 slack.com 2>&1 | head -2)
    DNS_OK=$(echo "$DNS_RESULT" | grep -c "has address")
    echo "  DNS slack.com: $([ $DNS_OK -gt 0 ] && echo OK || echo FAIL) | $DNS_RESULT" >> "$LOG"

    # 2. TCP connect test (port 443)
    TCP_RESULT=$(timeout 5 bash -c 'echo > /dev/tcp/slack.com/443' 2>&1 && echo OK || echo FAIL)
    echo "  TCP slack.com:443: $TCP_RESULT" >> "$LOG"

    # 3. Webhook test (se configurado)
    if [ -n "$SLACK_WEBHOOK" ]; then
        WH_RESULT=$(curl -s -o /dev/null -w "%{http_code}" \
            --connect-timeout 5 --max-time 10 \
            -X POST -H 'Content-Type: application/json' \
            -d '{"text":"[monitor] ping '"$TS"'"}' \
            "$SLACK_WEBHOOK" 2>&1)
        echo "  Webhook POST: HTTP $WH_RESULT" >> "$LOG"
    else
        echo "  Webhook: não configurado (SLACK_WEBHOOK_MAIN_URL não encontrado no .env)" >> "$LOG"
    fi

    # 4. API auth.test (se token configurado)
    if [ -n "$SLACK_TOKEN" ]; then
        AUTH_RESULT=$(curl -s --connect-timeout 5 --max-time 10 \
            -H "Authorization: Bearer $SLACK_TOKEN" \
            'https://slack.com/api/auth.test' 2>&1)
        AUTH_OK=$(echo "$AUTH_RESULT" | grep -c '"ok":true')
        AUTH_ERR=$(echo "$AUTH_RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('error',''))" 2>/dev/null)
        echo "  API auth.test: $([ $AUTH_OK -gt 0 ] && echo OK || echo FAIL) $AUTH_ERR" >> "$LOG"
    else
        echo "  API auth.test: token não configurado" >> "$LOG"
    fi

    echo "" >> "$LOG"

    [ $i -lt $ITERATIONS ] && sleep $INTERVAL
done

echo "=== Monitor finalizado $(date) ===" >> "$LOG"
