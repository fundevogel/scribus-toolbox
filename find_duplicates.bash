#!/bin/bash

##
# Finds all duplicate entries for given issue identifier
#
# Usage:
# bash find_duplicates.bash `ISSUE`
#
##

issue=$1

root_directory=$(dirname "$(dirname "$0")")
cd "$root_directory"/issues/"$issue"/src/csv || exit

while IFS= read -r isbn; do

    # Search for files containing ISBN, remove those without
    duplicates=$(grep -c "$isbn" ./*.csv | sed "/0/d")

    # Check occurences per file ..
    perfile="${duplicates: -1}"

    # .. and across all files
    infiles=$(echo "$duplicates" | wc -l)

    # If ISBN occurs more than once (per file or across files) ..
    if [[ $perfile -gt 1 ]] || [[ $infiles -gt 1 ]]; then

        # (1) Remove last two characters of each entry & put them all in one line
        files=$(echo "$duplicates" | sed 's/..$//' | paste -sd "" -)

        # (2) Remove leading './'
        files="${files:2}"

        # (3) Replace './' with ' & '
        files=${files//.\//\ &\ }

        # (4) Print the result
        printf "Duplicate found for %s in %s\\n" "$isbn" "$files"

    fi

# (1) & (2) Inject ISBNs from all `.csv` files
# (3) Sort them
# (4) Remove duplicates
done < <(cat ./*.csv | csvcut -d ";" -c 4 -e latin1 | sort | uniq)
