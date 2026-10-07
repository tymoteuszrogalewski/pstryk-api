<?php
// Skopiuj do config.php i uzupelnij. config.php jest w .gitignore.

// Klucz API z aplikacji Pstryk (Konto -> Integracje / API)
define('PSTRYK_API_KEY', 'sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
define('PSTRYK_PAUSE',   2);    // przerwa w sekundach miedzy miesiacami przy pobieraniu historii

// Baza MySQL / MariaDB. DB_NAME = '' -> zamiast zapisu do bazy wypisuje CSV na ekran.
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'pstryk');
define('DB_PASS', 'password');
define('DB_NAME', 'pstryk');
