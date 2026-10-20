# Revista — local PHP comic library

## Download and run

1. Download the repository ZIP and extract it, or clone this repository.
2. Install PHP 8.4+ with `pdo_sqlite` enabled and add PHP to PATH. On Windows, use https://windows.php.net/download/ and install the required Microsoft Visual C++ runtime. In `php.ini`, configure `extension_dir` to your PHP `ext` folder and enable `extension=pdo_sqlite`.
3. Restart VS Code after changing PATH. Open the extracted project folder.
4. Press **Ctrl+Shift+B** to run **Run Revista**, then open http://127.0.0.1:8000.
5. Stop with Ctrl+C in the task terminal before starting another server.

Or run from the project folder in PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start.ps1
```

The script uses the optional local `.runtime/php` installation when present, otherwise PHP on PATH. The runtime and personal database are not included in GitHub. All application images are included. A fresh database is created automatically; register a new account after downloading.

On other operating systems, install PHP with PDO SQLite and run:

```sh
php -S 127.0.0.1:8000 -t public
```

Keep the included `storage/` directory writable. No MySQL, Composer, npm, or Live Server extension is required. GitHub stores the source; GitHub Pages does not run this PHP application.

## Features

- Four-title catalog, title search, and genre filters.
- Comic details and readers for all four user-supplied image folders.
- Registration, login, logout, password hashing, and per-user bookmarks.
- SQLite is created automatically on first request in `storage/app.sqlite`.
- Prepared statements, escaped output, CSRF checks, session regeneration, and a five-failure/15-minute login limit per email.
- Responsive local styles; no remote ads, scripts, fonts, or trackers.

Register your own account; there are no default credentials. All four titles have local readers. Chapter labels follow the supplied links (1 for Sword Hound and Player, 0 for Reincarnator and Solo). Images were copied from the user-provided folders, not downloaded. Filename numbering is preserved and naturally sorted. Three supplied folders begin at 2; add any missing first image if needed.

## Project layout

- `public/index.php`: page controller and templates; only this directory should be served.
- `public/assets/`: images and styles.
- `app/bootstrap.php`: sessions, database, authentication, bookmark actions.
- `app/catalog.php`: catalog metadata and reader image lists.
- `storage/`: private database (do not commit it).
- `scripts/start.ps1`: local launch script.
- `scripts/smoke.py`: isolated HTTP integration checks; see below.

## Images and archived originals

The live site loads every image from `public/assets/manhwa/`. Upload this directory with the rest of `public`; it does not fetch artwork from another website or from your Desktop.

```text
public/assets/manhwa/
  sword-hound/      cover.png, hero.jpg, chapter-1/
  player/          cover.png, chapter-1/
  reincarnator/    cover.png, chapter-0/
  solo/            cover.jpg, chapter-0/
```

Put numbered JPG, JPEG, PNG, or WebP pages in the corresponding chapter folder. They appear automatically in numeric order. The current app supports one chapter per title.

`archive/` contains the old prototype, unused assets, and original input folders. These are preserved for reference and are not used by the site. Do not upload `archive/`, `.runtime/`, or any database files from `storage/`. Upload `storage/.gitkeep` to retain the empty writable database directory.

## Verification

Run `python scripts/smoke.py` with Python 3 installed. It launches a separate server on port 8017 and uses a temporary database, never your real accounts. Tests cover registration/login, duplicate email, validation, bookmarks, separate users, CSRF, SQL-injection input, escaped names, missing pages, static assets, private file isolation, and login rate limiting.

Keep the server bound to 127.0.0.1 for local development. Public deployment would need HTTPS and deployment-specific configuration; no artwork redistribution rights are implied.
