#!/bin/bash

# Configuration
PROMPT_FILE="DEEPSEEK_PROMPT.md"
FINISHED_FILE="DEEPSEEK_FINISHED.txt"
POLL_INTERVAL=10
RETRY_INTERVAL=15

echo "Starting OpenCode Agent Worker loop..."
echo "Polling for ${PROMPT_FILE} every ${POLL_INTERVAL} seconds..."

while true; do
  if [ -f "$PROMPT_FILE" ]; then
     echo "New task detected in ${PROMPT_FILE}! Delegating to OpenCode..."
     
     # Automatically retry if the API is overloaded or experiences traffic errors
     until yes | opencode run "Please read ${PROMPT_FILE} and execute its instructions to modify this plugin." < /dev/null; do
         echo "OpenCode/DeepSeek API returned an error. Retrying in ${RETRY_INTERVAL} seconds..."
         sleep $RETRY_INTERVAL
     done
     
     # Mark task as finished
     mv "$PROMPT_FILE" "$FINISHED_FILE"
     echo "Task completed successfully. Marked as finished."
  fi
  sleep $POLL_INTERVAL
done
