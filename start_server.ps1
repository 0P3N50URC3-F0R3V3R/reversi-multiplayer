Write-Host "Reversi server starting at http://localhost:8000" -ForegroundColor Green
Write-Host "Stop with Ctrl+C" -ForegroundColor Yellow
& "$PSScriptRoot\php\php.exe" -c "$PSScriptRoot\php\php.ini" -S localhost:8000 -t $PSScriptRoot
