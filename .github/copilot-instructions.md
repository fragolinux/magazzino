# Magazzino Componenti Elettronici - AI Coding Guide

## Project Overview
This is a PHP 8.0 warehouse management system for electronic components, running on Nginx + PHP-FPM + MariaDB via Docker. The app manages physical inventory locations, compartments, and components with full CRUD operations.

**Key Architecture:**
- `magazzino/warehouse/` - Core inventory workflows (locations, compartments, components)
- `magazzino/admin/` - User management (admin only)
- `magazzino/includes/` - Shared auth (`auth_check.php`), DB (`db_connect.php`), layout (`header.php`, `footer.php`)
- `overrides/db_connect.php` - Docker-specific DB config using environment variables
- Database schema: `magazzino_db.sql` (locations → compartments → components hierarchy)

## Database Structure
**Core tables:**
- `locations` - Physical storage locations (shelves, drawers)
- `compartments` - Subdivisions within locations (code: alphanumeric sorting)
- `components` - Electronic parts with category, quantity, manufacturer, supplier, datasheet URL, equivalents (JSON array)
- `categories` - Component classifications (resistors, capacitors, etc.)
- `users` - Login credentials (SHA-256 password hashing)

**Critical relationships:**
- Components link to one location and one compartment (both nullable)
- Compartments belong to locations; cascade delete not enforced in code

## Development Workflows

### Quick Start (Development)
```bash
# Clone and start dev environment (hot reload enabled)
./scripts/start-dev.sh
# Visit http://localhost
# Login: RG4Tech / 12345678
```

### Production Build
```bash
./scripts/start.sh  # Uses prebuilt images from ghcr.io/fragolinux
```

### Database Operations
```bash
# Backup database
./scripts/backup.sh  # Creates timestamped dump in backup/

# Restore database
./scripts/restore.sh backup/20260103_145904  # Restores from specific backup folder
```

### Manual Testing
No automated tests exist. Validate changes by:
1. Testing login flow (`login.php`)
2. CRUD operations on components (`warehouse/components.php`)
3. Filtering by location/compartment/category
4. Quantity adjustment (load/unload via AJAX modal)

## PHP Coding Conventions

### File Structure
- Use `lower_snake_case` for all PHP files (e.g., `add_component.php`, `edit_location.php`)
- Include auth check and DB connection at top of every protected page:
  ```php
  require_once '../includes/db_connect.php';
  require_once '../includes/auth_check.php';
  ```

### Database Queries
- **Always use PDO prepared statements** - never string concatenation
- Example pattern from `get_components.php`:
  ```php
  $query = "SELECT * FROM components WHERE location_id = ?";
  $stmt = $pdo->prepare($query);
  $stmt->execute([$location_id]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ```

### AJAX Endpoints
- Some endpoints return HTML fragments, others return JSON.
- Example HTML: `get_components.php` echoes table rows directly.
- Example JSON: `get_compartments.php` returns JSON for QR code selection.
- Frontend JavaScript replaces DOM content with response.

### Authentication
- Session-based auth (`$_SESSION['user_id']`)
- Password hashing: `hash('sha256', $password)` (existing convention)
- Auth guard: `auth_check.php` redirects to login if no session

### Output Escaping
- **Always** use `htmlspecialchars()` for user data in HTML
- Use `ENT_QUOTES` when data appears in HTML attributes
- Example: `htmlspecialchars($component['codice_prodotto'], ENT_QUOTES)`

## Docker Environment

### Environment Variables (.env file)
```bash
DB_ROOT_PASSWORD=root_password
DB_NAME=magazzino_db
DB_USER=magazzino_user
DB_PASS=magazzino_pass
MAGAZZINO_TAG=latest  # Optional: pin version (v1.1, v1.2, etc.)
```

### Development vs Production
- **Dev** (`docker-compose.dev.yml`): Mounts `./magazzino` as volume (hot reload)
- **Prod** (`docker-compose.yml`): Uses prebuilt images with baked-in code
- Both mount `overrides/db_connect.php` to inject environment-based DB credentials
- DB migrations run on container start via `docker/php/db_migrate.php` (idempotent).

### Data Persistence
- `data/db/` - MariaDB data files
- `data/nginx-logs/`, `data/php-logs/` - Service logs
- **Never commit `data/` directory**

## Frontend Patterns

### Tech Stack
- Bootstrap 5 (responsive grid, modals, forms)
- jQuery 3.6.0 + jQuery UI (DOM manipulation, AJAX)
- Font Awesome 6 (icons via `<i>` tags)

### AJAX Loading Pattern
See `components.php` for reference:
1. User selects filter dropdown
2. JavaScript triggers `$.get('get_components.php', params)`
3. Response HTML replaces `#components-body` content
4. Modal details loaded similarly (`view_component.php`)

### Common UI Components
- Modals: `#componentModal` (details), `#unloadModal` (quantity adjust)
- Filter dropdowns: Cascade (location → compartment → category)
- Action buttons: View (info icon), Edit (pen icon), Delete (trash icon)

## Special Features

### QR Code Workflow
- Admin-only QR generation (`warehouse/qrcodes.php` → `generate_qrcodes.php`)
- Uses `setting.IP_Computer` for the host in QR payloads
- Mobile flow handled by `warehouse/mobile_component.php`

### Compartment Alphanumeric Sorting
Query from `add_component.php`:
```php
ORDER BY REGEXP_REPLACE(code, '[0-9]', '') ASC,
         CAST(REGEXP_REPLACE(code, '[^0-9]', '') AS UNSIGNED) ASC
```
This sorts "A1, A2, A10, B1" correctly (not "A1, A10, A2, B1").

### Equivalents Field
- Stored as JSON array in `components.equivalents` column
- Example: `["BC547", "2N2222", "PN2222"]`
- Input: comma-separated string converted to JSON on save

### Quick Add Compartment
- AJAX endpoint `ajax_add_compartment.php` creates compartment inline
- Returns new compartment ID to populate dropdown without page reload

## Security Notes
- **Do not commit real credentials** - use `.env` (gitignored)
- Production: Consider migrating from SHA-256 to `password_hash()` with bcrypt
- SQL injection protection: Enforce prepared statements in all new code
- XSS protection: Never output unescaped user data

## Credits
Based on original work by RG4Tech (Gabriele Riva). See [project homepage](https://rg4tech.altervista.org/forum/thread-463-post-576.html).
