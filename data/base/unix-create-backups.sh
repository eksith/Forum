#!/bin/bash

# Change this if the databases are located elsewhere
data=$(dirname $0)

# Timestamp : year-month-date (this format is safe for daily backups)
now=$(date +"%F")

# Keep these the same if you haven't renamed the files
b_src=$data/board.sqlite
b_dest=$data/backups/board-backup-$now.sql

s_src=$data/session.sqlite
s_dest=$data/backups/session-backup-$now.sql

echo "Creating database backups..."

sqlite3 $b_src .dump > $b_dest
sqlite3 $s_src .dump > $s_dest

echo "Done"
