<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2026-02-02 14:16:36 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-02-02 19:53:18
*/

/*
 * Footer Personalizzato per Sito Personale
 */

// Ricarica config se necessario
if (!isset($config)) {
    $config = $pdo->query("SELECT * FROM personal_site_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}
?>
    <footer class="personal-footer mt-auto">
        <div class="container">
            <?php if (!empty($config['footer_content'])): ?>
                <?= $config['footer_content'] ?>
            <?php else: ?>
                <!-- Footer di default -->
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($config['site_title'] ?? 'Il Mio Sito', ENT_QUOTES, 'UTF-8') ?>. Tutti i diritti riservati.</p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <a href="#home" class="text-decoration-none me-3">
                            <i class="fa-solid fa-arrow-up me-1"></i>Torna su
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    
    <script src="<?= BASE_PATH ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Animazione elementi allo scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, observerOptions);
        
        document.addEventListener('DOMContentLoaded', () => {
            // Osserva tutti gli elementi con classe scroll-animate
            document.querySelectorAll('.scroll-animate').forEach(el => {
                observer.observe(el);
            });
            
            // Smooth scroll per i link della navbar e torna su
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');
                    // Se il link è un anchor (es: #home, #section-1), fai lo scroll smooth
                    if (href !== '#' && href.length > 1) {
                        e.preventDefault();
                        // Scorri verso l'inizio della pagina per #home
                        if (href === '#home') {
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                        } else {
                            // Scorri verso la sezione specifica
                            const target = document.querySelector(href);
                            if (target) {
                                target.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }
                        }
                    }
                });
            });
            
            // Highlight active nav item durante lo scroll
            const sections = document.querySelectorAll('.personal-section');
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            window.addEventListener('scroll', () => {
                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (window.pageYOffset >= (sectionTop - 100)) {
                        current = section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>
