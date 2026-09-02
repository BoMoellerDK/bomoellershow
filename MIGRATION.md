# Migreringsstatus

Starteren er konverteret til Bo Møller showet:

- podcastkonfigurationen er skilt fra motoren
- RSS-feed og fallback-data er udskiftet
- YouTube-, Spotify- og Apple Podcasts-profiler er tilknyttet
- episode-numre læses fra `itunes:episode`
- vært, portræt, indhold, metadata og visuel identitet er udskiftet
- tests bruger Bo Møller-showets egne fire episoder

Produktionsdomænet er `bomoeller.dk`, og deployment til Simply.com er
automatiseret i `.github/workflows/deploy.yml`. Før første deployment skal de
tre repository secrets `FTP_SERVER`, `FTP_USERNAME` og `FTP_PASSWORD` oprettes
på GitHub. Eventuel analytics er fortsat et separat valg.
