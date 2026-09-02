# Fra SaaS Købmænd til Bo Møller Podcast

Dette er en starter-copy, ikke en permanent fork. De to podcasts skal kunne
udvikles og deployes uafhængigt.

## 1. Aftal identiteten

Følgende skal være kendt, før sitet kan gøres produktionsklart:

- endeligt podcastnavn og en kort beskrivelse
- primært domæne og eventuelt kort delingsdomæne
- RSS-feed
- Spotify- og Apple Podcasts-links
- YouTube-kanal, hvis episoderne også udgives som video
- værtsbio, portræt, nyhedsbrev og relevante profil-links
- logo, farver, typografi og cover-art
- hosting/deployment og eventuel analytics/statusside

## 2. Skil konfiguration fra motoren

Før den visuelle tilpasning bør podcastens konfiguration flyttes ud af
\`public_html/index.php\` til én config-fil. Den bør samle navn, URLs, feeds,
værter, platforme, emner og CTA'er. Det reducerer risikoen for, at gammel
SaaS Købmænd-tekst bliver stående i metadata eller på sjældne sider.

Der er stadig hårdkodet SaaS Købmænd-indhold flere steder i \`index.php\`, bl.a.:

- sidetitler, hero, footer, 404 og nyhedsbrevs-CTA'er
- emnekategorier og tekstnormalisering til YouTube-match
- miljøvariabelnavne og cachefilnavne
- analytics-nøgle og driftslink

## 3. Udskift podcastdata

- Erstat \`data/podcast-rss-fallback.xml\` med et valideret snapshot af det nye feed.
- Byg \`data/youtube-catalog-seed.json\` fra den nye YouTube-kanal, eller slå
  YouTube fra, indtil kanalen er klar.
- Tilpas test-fixtures, forventede episodenumre, titler og minimumsantal.
- Bevar matching-princippet: katalog først, derefter titel, episodenummer og dato.

Gamle RSS- og YouTube-data må ikke komme med i den første produktion.

## 4. Lav en selvstændig visuel identitet

Genbrug informationsarkitekturen, men ikke bare logoet og farverne. Bevar gerne:

- tydelig seneste episode
- oversigt med søgning/emner
- dedikerede episodesider
- lyd/video uden tredjeparts-iframe før brugeren trykker play
- stærk metadata og strukturerede data

Udskift alle filer i \`public_html/assets/hosts/\`, logo/favicons, manifestnavne,
farvevariabler og synlige tekster. Kontrollér også \`robots.txt\`.

## 5. Gør tests generiske

De kopierede tests er nyttige som sikkerhedsnet, men forventer lige nu SaaS
Købmænds 72+ episoder, konkrete slugs, værter, billeder og domæne. Flyt de
forventninger, der beskriver podcasten, til fixtures/config, og behold de
generiske assertions for HTTP-status, komplet HTML, redirects, sitemap,
Open Graph, AudioObject og VideoObject.

## 6. Deployment til sidst

Deployment-workflowet er bevidst ikke kopieret. Opret det først, når domæne,
webhotel og FTP-rod er afklaret. Hvis Simply bruges igen, kan samme FTPS-princip
genbruges med nye repository secrets. Push ikke gamle alias-domæner eller den
gamle FTP-state til det nye webhotel.

## Anbefalet arbejdsgang i Conductor

Tilføj \`bomoellershow\` som et separat repository i Conductor. Lav derefter
afgrænsede workspaces til:

1. konfiguration og nye feed-data
2. branding/design og indhold
3. tests, SEO/GEO og deployment

Det holder arbejdet på den nye podcast adskilt fra SaaS Købmænds branches og
gør hvert trin let at reviewe.
