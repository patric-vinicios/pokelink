#!/usr/bin/env bash
# Runs every gate in sequence and reports a pass/fail summary at the end.
# Does not stop at the first failure, so one run shows everything that's red.
set -uo pipefail
cd "$(dirname "$0")"

gates=(format static-analysis lint architecture duplication security unused-deps test)
failed=()

for gate in "${gates[@]}"; do
    echo "=== ${gate} ==="
    if ! "./${gate}.sh"; then
        failed+=("$gate")
    fi
    echo
done

if [[ ${#failed[@]} -eq 0 ]]; then
    echo "All gates passed."
    exit 0
fi

echo "Failed gates: ${failed[*]}"
exit 1
