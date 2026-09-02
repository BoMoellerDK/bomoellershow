# Bo Møller showet – website

Et selvstændigt podcastsite for **Bo Møller showet**, bygget på den robuste
RSS- og episodemotor fra SaaS Købmænd, men med egen konfiguration, identitet,
indhold og testdata.

## Indhold og integrationer

- Spotify for Podcasters RSS-feed med lokal fallback
- episodeoversigt og permanente episodesider
- lydafspiller og YouTube-video med klik-først facade
- Spotify-, Apple Podcasts- og YouTube-links
- sitemap, `llms.txt`, Open Graph og schema.org-data
- lokal, kurateret sammenkobling af de første fire YouTube-episoder

Podcastens identitet ligger i `config/podcast.php`. Produktionsdomænet kan
overskrives med `BOMOELLERSHOW_SITE_URL` og `BOMOELLERSHOW_SHORT_URL`.

## Lokal test

Kræver PHP 7.4+ med `mbstring`, `SimpleXML` og `DOM`.

```sh
bash tests/run-release.sh
```

## Lokal forhåndsvisning

```sh
BOMOELLERSHOW_RSS_URL="file://$PWD/data/podcast-rss-fallback.xml" \
php -S 127.0.0.1:8000 -t public_html tests/router.php
```

Åbn derefter `http://127.0.0.1:8000`.

## Automatisk deployment

`.github/workflows/deploy.yml` tester og deployer automatisk til Simply.com
via FTPS ved hvert push til `main`. Workflowet kan også startes manuelt fra
fanen Actions på GitHub.

Tilføj disse tre repository secrets under `Settings -> Secrets and variables
-> Actions` på GitHub:

- `FTP_SERVER`: FTP-serveren fra Simply.com, normalt `ftp.simply.com`
- `FTP_USERNAME`: webhotellets FTP-brugernavn
- `FTP_PASSWORD`: webhotellets FTP-adgangskode

Loginoplysningerne findes i Simply.com-kontrolpanelet under webhotellets
`Administration -> Loginoplysninger`. Workflowet uploader repoets rod til
FTP-roden, så `public_html`, `config` og `data` bevarer den mappestruktur,
applikationen forventer.
