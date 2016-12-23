@echo off

REM Timestamp : year-month-date (this format is safe for daily backups)
set bkp=%DATE:~-4%-%DATE:~4,2%-%DATE:~7,2%

if exist sqlite3.exe (
	echo Creating database backups...
	
	REM Keep these the same if you haven't renamed the files
	sqlite3.exe session.sqlite .dump > backups\session-backup-%bkp%.sql
	sqlite3.exe board.sqlite .dump > backups\board-backup-%bkp%.sql

	echo Done
	timeout /t 5
) else (
	echo Make sure sqlite3.exe is in the same folder
	echo If you don't have it yet, get it from : https://sqlite.org/download.html
	echo Look for "sqlite-tools" under the Windows category
	pause
)
