<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-02 16:08:59 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 19:49:51
*/

/*
 * Menu di navigazione per Sito Personale
 * Navbar completamente separata dal magazzino
 */

// Se non ci sono le variabili necessarie, caricale
if (!isset($config)) {
    try {
        $config = $pdo->query("SELECT * FROM personal_site_config ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $config = ['site_title' => 'Il Mio Sito'];
    }
}

if (!isset($sections)) {
    try {
        $sections = $pdo->query("SELECT * FROM personal_site_sections WHERE enabled = 1 ORDER BY section_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $sections = [];
    }
}

$siteTitle = htmlspecialchars($config['site_title'] ?? 'Il Mio Sito', ENT_QUOTES, 'UTF-8');
$logoPath = isset($config['logo_path']) && !empty($config['logo_path']) 
    ? BASE_PATH . 'assets/personal_site/' . basename($config['logo_path']) 
    : BASE_PATH . 'assets/img/logo.jpg';
$themePreset = $config['theme_preset'] ?? 'modern_minimal';
?>

<style>
.personal-navbar {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 1rem 0;
}
.personal-navbar .navbar-nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}
.personal-navbar .nav-link {
    margin: 0 0.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}
.personal-navbar .nav-link:hover {
    opacity: 0.8;
}
</style>

<nav class="navbar navbar-expand-lg personal-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="<?= BASE_PATH ?>personal_home.php" title="Home">
      <img src="<?= $logoPath ?>" alt="<?= $siteTitle ?>"
        style="height: 40px; object-fit: contain; margin-right: 0.5rem;">
      <span class="fw-bold d-none d-sm-inline"><?= $siteTitle ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#personalNavbarMenu"
      aria-controls="personalNavbarMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="personalNavbarMenu">
      <ul class="navbar-nav mx-auto">
        <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): 
                        $menuLabel = htmlspecialchars($section['menu_label'], ENT_QUOTES, 'UTF-8');
                        $anchor = 'section-' . $section['id'];
                    ?>
        <li class="nav-item">
          <a class="nav-link" href="#<?= $anchor ?>">
            <?= $menuLabel ?>
          </a>
        </li>
        <?php endforeach; ?>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav">
        <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
        <li class="nav-item">
          <a class="nav-link btn btn-primary text-white ms-2" href="<?= BASE_PATH ?>index.php" title="Accedi al magazzino">
            <i class="fa-solid fa-warehouse me-1"></i>
            <span class="d-sm-none d-md-inline">Magazzino</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_PATH ?>logout.php">
            <i class="fa-solid fa-right-from-bracket me-1"></i>Logout (<?= htmlspecialchars($_SESSION['username']) ?>)
          </a>
        </li>
        <?php else: ?>
        <li class="nav-item">
          <a class="nav-link btn btn-primary text-white ms-2" href="<?= BASE_PATH ?>login.php" title="Login">
            <i class="fa-solid fa-right-to-bracket me-1"></i>
            <span class="d-sm-none d-md-inline">Login</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>