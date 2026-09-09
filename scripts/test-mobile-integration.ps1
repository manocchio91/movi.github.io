param(
    [string]$Device = "emulator-5554"
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
$flutter = if (Get-Command flutter -ErrorAction SilentlyContinue) {
    "flutter"
} elseif (Test-Path "C:\src\flutter\bin\flutter.bat") {
    "C:\src\flutter\bin\flutter.bat"
} else {
    throw "Flutter non trovato nel PATH o in C:\src\flutter."
}

Push-Location $projectRoot
try {
    $compose = @(
        "compose", "-p", "movi-e2e",
        "-f", "docker-compose.yml",
        "-f", "frontend/e2e/docker-compose.e2e.yml"
    )
    docker @compose up --build -d --wait
    docker @compose exec -T app php artisan migrate:fresh --seed --force
    docker @compose exec -T app php artisan db:seed --class='Database\Seeders\MobileIntegrationTestSeeder' --force
    Push-Location (Join-Path $projectRoot "mobile")
    try {
        & $flutter pub get
        & $flutter test integration_test -d $Device `
            --dart-define="API_BASE_URL=http://10.0.2.2:18081/api/v1"
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    } finally {
        Pop-Location
    }
} finally {
    if ($compose) {
        docker @compose down -v
    }
    Pop-Location
}
