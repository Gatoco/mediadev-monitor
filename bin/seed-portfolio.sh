#!/bin/bash
# seed-portfolio.sh — siembra los 28 sitios del portafolio mediadev.cl (demo).
# Separado de e2e-assert.sh: NO corre la suite E2E, NO toca mysql/fixtures.
# Idempotente: regenera config/sites.php siempre igual.
#
# Clasificación objetivo (design demo-panel-publico):
#   25 wp con token dummy  (todos los aliases WP menos mediadev.cl)
#    1 hardened sin token  (mediadev.cl → wp-degraded real)
#    2 non-wp              (cardiomedica.cl, vinadetoroalexander.cl)
#    2 down                (hotelvivomontana.cl, septimaruta.cl)
set -euo pipefail
cd "$(dirname "$0")/.."

# Backup del seed actual (una sola vez por fecha/hora)
if [ -f config/sites.php ]; then
    cp config/sites.php "/tmp/sites.php.backup-$(date +%Y%m%d-%H%M%S)"
fi

cat > config/sites.php <<'PHP'
<?php
// Generado por bin/seed-portfolio.sh (demo portfolio mediadev.cl) — gitignored.
return [
    ['url'=>'http://diariotalca.cl:80','name'=>'diariotalca.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://casa-humana.cl:80','name'=>'casa-humana.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://agriculthor.cl:80','name'=>'agriculthor.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://aikidomaipukai.com:80','name'=>'aikidomaipukai.com','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://alimentate.cl:80','name'=>'alimentate.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://carrosdelmaule.cl:80','name'=>'carrosdelmaule.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://cecinasdonjacinto.cl:80','name'=>'cecinasdonjacinto.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://conequip.cl:80','name'=>'conequip.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://estadioespanoltalca.cl:80','name'=>'estadioespanoltalca.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://hubot.cl:80','name'=>'hubot.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://innovacion.ucm.cl:80','name'=>'innovacion.ucm.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://integravita.cl:80','name'=>'integravita.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://itma.cl:80','name'=>'itma.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://laboratorioaleman.cl:80','name'=>'laboratorioaleman.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://macmedica.cl:80','name'=>'macmedica.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://mapeko.org:80','name'=>'mapeko.org','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://meymaq.cl:80','name'=>'meymaq.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://morissalud.cl:80','name'=>'morissalud.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://myfcardiosalud.cl:80','name'=>'myfcardiosalud.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://okrental.cl:80','name'=>'okrental.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://radiataforestal.cl:80','name'=>'radiataforestal.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://servivalchile.cl:80','name'=>'servivalchile.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://villalegre.cl:80','name'=>'villalegre.cl','type'=>'wp','wp_user'=>'monitor','token'=>'demo-token-0001'],
    ['url'=>'http://cardiomedica.cl:80','name'=>'cardiomedica.cl','type'=>'non-wp','wp_user'=>null,'token'=>null],
    ['url'=>'http://vinadetoroalexander.cl:80','name'=>'vinadetoroalexander.cl','type'=>'non-wp','wp_user'=>null,'token'=>null],
    ['url'=>'http://mediadev.cl:80','name'=>'mediadev.cl','type'=>'auto','wp_user'=>null,'token'=>null],
    ['url'=>'http://hotelvivomontana.cl:80','name'=>'hotelvivomontana.cl','type'=>'auto','wp_user'=>null,'token'=>null],
    ['url'=>'http://septimaruta.cl:80','name'=>'septimaruta.cl','type'=>'auto','wp_user'=>null,'token'=>null],
];
PHP

echo "sites.php sembrado con 28 sitios (25 wp + 1 hardened + 2 non-wp + 2 down)"