#!/bin/bash

##
# Finds all entries without proper age recommendation for given issue identifier
#
# Usage:
# bash check_ages.bash `ISSUE`
#
##

issue=$1

root_directory=$(dirname "$(dirname "$0")")
cd "$root_directory"/issues/"$issue"/dist/csv || exit

while IFS= read -r isbn; do

    # Print the result
    printf "Found improper age recommendation for %s\\n" "$isbn"

# (1) Inject all lines containing strings indicating improper age recommendation
# (2) Choose only entries from 'ISBN' and 'Age recommendation' columns
# (3) Select first part of resulting string, being the ISBN
done < <(grep -h "Keine Altersangabe\|bis" ./*.csv | csvcut -c 8,9 | cut -d "," -f 1)
