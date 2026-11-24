<#
.SYNOPSIS
    Builds the installable package of Mucronix Reviews.

.DESCRIPTION
    src/com_mucronixreviews, src/plg_system_mucronixreviews and src/plg_content_mucronixreviews become one archive each, and all go
    into packages/ of the package archive, next to the content of src/pkg_mucronixreviews.
    LICENSE.txt is added to all. The result is build/pkg_mucronixreviews-<version>.zip.

    The build stops, and leaves no archive, when the versions differ, when a PHP file of the package
    has no licence header or no defined('_JEXEC') line, when a PHP file of the package or of tests/
    does not pass php -l, or when a banned word is in a file published with the source. The banned words are
    listed in local-checks.txt, which is not published; without it that check is skipped.
    The finished archive is removed when its manifest does not name the update server and the changelog
    of the public repository, or when update/pkg_mucronixreviews.xml does not say the version of the
    manifest, the address of its release, Joomla 6 and PHP 8.3.

    The same sources give the same archive byte for byte: the entries are sorted and carry a
    constant time.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File build.ps1
#>

param(
    # PHP 8.3, for php -l: the one in PATH unless named
    [string]$Php = 'php'
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root    = $PSScriptRoot
$src     = "$root/src"
$build   = "$root/build"
$license = [System.IO.File]::ReadAllBytes("$root/LICENSE.txt")
$stamp   = New-Object System.DateTimeOffset(2026, 1, 1, 0, 0, 0, [System.TimeSpan]::Zero)
$utf8    = New-Object System.Text.UTF8Encoding($false)

$licenceLine = '@license     GNU General Public License version 2 or later'

# The public repository: its branch main serves the update file and the changelog, its releases the archives
$updateServer = 'https://raw.githubusercontent.com/mucronix/pkg_mucronixreviews/main/update/pkg_mucronixreviews.xml'
$changelogUrl = 'https://raw.githubusercontent.com/mucronix/pkg_mucronixreviews/main/update/changelog.xml'
$downloadUrl  = 'https://github.com/mucronix/pkg_mucronixreviews/releases/download/v{0}/pkg_mucronixreviews-{0}.zip'

# Banned words and the folders not published with the source: in local-checks.txt, kept on the machine of the
# build and never published. A line "skip <folder>" names a folder, any other line is a banned word, matched
# case-sensitively as a regular expression; lines starting with # are comments. Without the file the check
# of banned words is skipped, and the build says so.
$localChecks  = "$root/local-checks.txt"
$notPublished = @()
$banned       = @()

if (Test-Path -LiteralPath $localChecks) {
    foreach ($line in [System.IO.File]::ReadAllLines($localChecks, $utf8)) {
        $line = $line.Trim()

        if ($line -eq '' -or $line.StartsWith('#')) { continue }
        if ($line -match '^skip\s+(.+)$') { $notPublished += $Matches[1].TrimEnd('/'); continue }
        $banned += $line
    }
}

function Get-ManifestVersion([string]$path) {
    return ([xml][System.IO.File]::ReadAllText($path)).extension.version
}

# Every file of a folder as entry name => bytes, in a dictionary that sorts by ordinal
function Read-Tree([string]$folder) {
    $entries = New-Object 'System.Collections.Generic.SortedDictionary[string,byte[]]' ([System.StringComparer]::Ordinal)
    $base    = (Resolve-Path $folder).Path.TrimEnd('\') + '\'

    Get-ChildItem -LiteralPath $folder -Recurse -File | ForEach-Object {
        $entries[$_.FullName.Substring($base.Length).Replace('\', '/')] = [System.IO.File]::ReadAllBytes($_.FullName)
    }

    return , $entries
}

function Write-Archive([string]$path, $entries) {
    $zip = [System.IO.Compression.ZipFile]::Open($path, [System.IO.Compression.ZipArchiveMode]::Create)

    try {
        foreach ($name in $entries.Keys) {
            $entry               = $zip.CreateEntry($name, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $stamp

            $stream = $entry.Open()
            try { $stream.Write($entries[$name], 0, $entries[$name].Length) } finally { $stream.Dispose() }
        }
    } finally { $zip.Dispose() }
}

# One version in all three manifests, a package of mixed versions must not get out
$version = Get-ManifestVersion "$src/pkg_mucronixreviews/pkg_mucronixreviews.xml"

foreach ($manifest in "$src/com_mucronixreviews/mucronixreviews.xml", "$src/plg_system_mucronixreviews/mucronixreviews.xml", "$src/plg_content_mucronixreviews/mucronixreviews.xml") {
    if ((Get-ManifestVersion $manifest) -ne $version) {
        throw "The version in $manifest differs from the version of the package, $version"
    }
}

if ((Get-Content -Raw "$src/com_mucronixreviews/media/joomla.asset.json" | ConvertFrom-Json).version -ne $version) {
    throw "The version in joomla.asset.json differs from the version of the package, $version"
}

# --- Checks before anything is built. Every problem is listed, then the build stops.
$problems = @()

# Every PHP file of the package: the licence header, the line that stops a direct call, php -l
foreach ($file in Get-ChildItem -LiteralPath $src -Recurse -File -Filter '*.php') {
    $name = $file.FullName.Substring($root.Length + 1).Replace('\', '/')
    $text = [System.IO.File]::ReadAllText($file.FullName, $utf8)

    if (-not $text.Contains($licenceLine)) { $problems += "$name has no licence header" }
    if (-not $text.Contains("defined('_JEXEC') or die;")) { $problems += "$name has no defined('_JEXEC') or die; line" }
}

if (-not (Test-Path $Php)) {
    $problems += "PHP not found at $Php, pass -Php"
} else {
    foreach ($file in Get-ChildItem -LiteralPath $src, "$root/tests" -Recurse -File -Filter '*.php') {
        $lint = & $Php -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) { $problems += "php -l $($file.FullName.Substring($root.Length + 1))`: $($lint -join ' ')" }
    }
}

# Banned words in the package and in everything published with the source
if (-not $banned) {
    Write-Warning 'local-checks.txt not found or empty: the check of banned words is skipped'
}

$pattern = '(?-i)(?:' + ($banned -join '|') + ')'

foreach ($file in $(if ($banned) { Get-ChildItem -LiteralPath $root -Recurse -File -Force })) {
    $name = $file.FullName.Substring($root.Length + 1).Replace('\', '/')

    if ($name -match '^(build|archive|\.git)/' -or $name -eq 'local-checks.txt' -or @($notPublished | Where-Object { $name.StartsWith("$_/") })) { continue }
    if ($name -cmatch $pattern) { $problems += "the name of ${name} holds a banned word" }
    if ($file.Extension -notin '.php', '.xml', '.ini', '.js', '.css', '.json', '.md', '.txt', '.ps1', '.sql', '.svg', '.html' -and $file.Name -notin '.gitignore', '.gitattributes') { continue }

    $number = 0
    foreach ($line in ([System.IO.File]::ReadAllText($file.FullName, $utf8) -replace "`r`n", "`n").Split("`n")) {
        $number++
        if ($line -cmatch $pattern) { $problems += "${name}:$number holds the banned word '$($Matches[0])'" }
    }
}

if ($problems) {
    [Console]::Error.WriteLine('nothing was built:')
    foreach ($problem in $problems) { [Console]::Error.WriteLine('  ' + $problem) }
    exit 1
}

if (Test-Path $build) {
    Remove-Item -LiteralPath $build -Recurse -Force
}

New-Item -ItemType Directory -Path "$build/packages" | Out-Null

$component = Read-Tree "$src/com_mucronixreviews"
$component['admin/LICENSE.txt'] = $license
Write-Archive "$build/packages/com_mucronixreviews.zip" $component

$plugin = Read-Tree "$src/plg_system_mucronixreviews"
$plugin['LICENSE.txt'] = $license
Write-Archive "$build/packages/plg_system_mucronixreviews.zip" $plugin

$content = Read-Tree "$src/plg_content_mucronixreviews"
$content['LICENSE.txt'] = $license
Write-Archive "$build/packages/plg_content_mucronixreviews.zip" $content

$package = Read-Tree "$src/pkg_mucronixreviews"
$package['LICENSE.txt'] = $license
$package['packages/com_mucronixreviews.zip'] = [System.IO.File]::ReadAllBytes("$build/packages/com_mucronixreviews.zip")
$package['packages/plg_system_mucronixreviews.zip'] = [System.IO.File]::ReadAllBytes("$build/packages/plg_system_mucronixreviews.zip")
$package['packages/plg_content_mucronixreviews.zip'] = [System.IO.File]::ReadAllBytes("$build/packages/plg_content_mucronixreviews.zip")

$zipPath = "$build/pkg_mucronixreviews-$version.zip"
Write-Archive $zipPath $package

Remove-Item -LiteralPath "$build/packages" -Recurse -Force

# --- What ships: the manifest of the package is read back from the finished archive. A wrong update server
# leaves every site without updates, and nothing would say so.
$problems = @()
$zip      = [System.IO.Compression.ZipFile]::OpenRead($zipPath)

try {
    $entry  = $zip.GetEntry('pkg_mucronixreviews.xml')
    $reader = New-Object System.IO.StreamReader($entry.Open(), $utf8)
    try { $packed = [xml]$reader.ReadToEnd() } finally { $reader.Dispose() }
} finally { $zip.Dispose() }

$servers = @($packed.extension.updateservers.server)

if ($servers.Count -ne 1 -or $servers[0].InnerText.Trim() -cne $updateServer -or [string]$servers[0].type -cne 'extension') {
    $problems += "the update server in the archive is not the one of type extension at $updateServer"
}

if ([string]$packed.extension.changelogurl -cne $changelogUrl) {
    $problems += "the changelog in the archive is not $changelogUrl"
}

if ([string]$packed.extension.version -cne $version) {
    $problems += "the manifest in the archive does not say $version"
}

# --- The update file says the same version and the address of the release of this archive
$updates = [xml][System.IO.File]::ReadAllText("$root/update/pkg_mucronixreviews.xml", $utf8)
$update  = @($updates.updates.update)

if ($update.Count -ne 1) {
    $problems += 'the update file does not hold exactly one update'
} else {
    $update = $update[0]

    if ([string]$update.version -cne $version) { $problems += "the update file says $($update.version), the manifest $version" }
    if ([string]$update.element -cne 'pkg_mucronixreviews' -or [string]$update.type -cne 'package' -or [string]$update.client -cne 'site') { $problems += 'the update file does not describe the package pkg_mucronixreviews of the site' }
    if ($update.downloads.downloadurl.InnerText.Trim() -cne ($downloadUrl -f $version)) { $problems += "the download address in the update file is not $($downloadUrl -f $version)" }
    if ([string]$update.targetplatform.version -cne '6\.[0-9]+' -or [string]$update.php_minimum -cne '8.3.0') { $problems += 'the update file is not for Joomla 6 and PHP 8.3' }

    $sum = @($updates.SelectNodes('//update/sha256'))
    if ($sum.Count -gt 0 -and $sum[0].InnerText.Trim() -cnotmatch '^[0-9a-f]{64}$') { $problems += 'the update file holds a sha256 that is not a checksum: an empty one makes Joomla refuse the update' }
}

if ($problems) {
    Remove-Item -LiteralPath $zipPath -Force
    [Console]::Error.WriteLine('the archive was removed:')
    foreach ($problem in $problems) { [Console]::Error.WriteLine('  ' + $problem) }
    exit 1
}

Write-Host "Built $zipPath"
Write-Host "  com_mucronixreviews: $($component.Count) files, plg_system_mucronixreviews: $($plugin.Count) files, plg_content_mucronixreviews: $($content.Count) files, pkg_mucronixreviews: $($package.Count) files"
Write-Host "  sha256 $((Get-FileHash -Algorithm SHA256 -LiteralPath $zipPath).Hash.ToLower())"
