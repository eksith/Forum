@echo off

if exist sqlite3.exe (
	echo Creating databases...
	
	REM Keep these the same if you haven't renamed 'board.sqlite'
	if exist session.sqlite (
		echo File conflict: session.sqlite already exists
	) else (
		sqlite3.exe session.sqlite < session.sqlite.sql
		echo Done session.sqite
	)
	
	if exist board.sqlite (
		echo File conflict: board.sqlite already exists
	) else (
		sqlite3.exe board.sqlite < board.sqlite.sql
		echo Done board.sqite
	)

	timeout /t 5
) else (
	echo Make sure sqlite3.exe is in the same folder
	echo If you don't have it yet, get it from : https://sqlite.org/download.html
	echo Look for "sqlite-tools" under the Windows category
	pause
)
