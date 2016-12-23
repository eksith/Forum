#!/bin/bash

# Change this if the databases are located elsewhere
data=$(dirname $0)

# Keep these the same if you haven't renamed 'board.sqlite'
b_src=$data/board.sqlite.sql
b_dest=$data/board.sqlite

s_src=$data/session.sqlite.sql
s_dest=$data/session.sqlite

echo "Creating databases..."

if [ -f "$b_dest" ]; then
	echo "File conflict: $b_dest already exists"
else
	sqlite3 $b_dest < $b_src
	echo "Done $b_dest"
fi

if [ -f "$s_dest" ]; then
	echo "File conflict: $s_dest already exists"
else
	sqlite3 $s_dest < $s_src
	echo "Done $s_dest"
fi

read -rsp $'Press any key or wait 5 seconds to continue...\n' -n 1 -t 5;
