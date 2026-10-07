#!/bin/bash
# Pobiera wczoraj, dzis i jutro. Do crona, np. co godzine:  15 * * * *  /sciezka/pstryk-api/go.sh
php "$(dirname "$0")/pstryk_day.php" "$@"
