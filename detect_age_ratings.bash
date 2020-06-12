#!/bin/bash

##
# Detects all entries without proper age recommendation for given issue identifier
#
# Usage:
# bash detect_age_ratings.bash `ISSUE`
#
##

issue=$1

root_directory=$(dirname "$(dirname "$0")")
cd "$root_directory"/issues/"$issue"/dist/csv || exit

newline=$'\n'
result="Results:${newline}"

while IFS= read -r line; do
    # Extract ISBN & age rating
    isbn=$(echo "$line" | cut -d "," -f 1)
    age_rating=$(echo "$line" | cut -d "," -f 2)

    # Save result
    result+="Found improper age recommendation for $isbn: $age_rating${newline}"

# (1) Inject all `.csv` files
# (2) Choose only entries from 'ISBN' and 'Age recommendation' columns
# (3) Select lines containing strings indicating improper age recommendation
# (4) Remove duplicates
done < <(cat ./*.csv | csvcut -c 8,9 | grep "Altersangabe\|bis" | uniq)


file=../../meta/age-ratings.txt

# Check if age ratings report exists ..
if [ ! -f $file ]; then
    # .. if it does, then `printf` results to file
    echo "$result" >$file
fi
