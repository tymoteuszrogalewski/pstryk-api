#!/bin/bash
# Pobiera historie miesiacami.  Np.:  ./history.sh 2025-01-01   albo   ./history.sh 2025-01-01 2025-12-31
php "$(dirname "$0")/pstryk_history.php" "$@"
