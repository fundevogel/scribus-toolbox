#!/bin/bash

##
# Sets up new issue
#
# Usage:
# bash setup.bash `ISSUE`
#
##

issue=$1

root_directory=$(dirname "$(dirname "$0")")

mkdir -p "$root_directory"/issues/"$issue"
cd "$root_directory"/issues/"$issue" || exit

# Preparing directory structure
# (1) Generate skeleton
for dir in src/csv \
           src/templates \
           meta \
           dist/csv \
           dist/images \
           dist/images \
           dist/documents/pdf \
           dist/documents/mails \
           dist/templates/partials
do
    mkdir -p "$dir"
done

# (2) Move & convert CSV (if it exists)
if [ -d "../../$issue" ]; then
    mv "../../$issue" src/csv/master

    for file in src/csv/master/*.csv; do
        base_name=$(basename "$file")
        iconv --from-code=ISO8859-1 --to-code=UTF-8 "$file" | tr -d '\015' > "src/csv/$base_name"
    done
fi
