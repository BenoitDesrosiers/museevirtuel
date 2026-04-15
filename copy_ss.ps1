$files = Get-ChildItem -LiteralPath 'C:\Users\Adam\Herd\Muse\.claude' -Filter '*.png'
foreach ($f in $files) {
    Copy-Item -LiteralPath $f.FullName -Destination 'C:\Users\Adam\Herd\Muse\public\ss2.png'
    Write-Host "Copied"
}
