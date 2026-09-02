# Bo Møller Podcast – website

Dette repo er oprettet som et selvstændigt website for Bo Møllers nye podcast.

Branchen \`reuse-podcast-engine\` indeholder en foreløbig kopi af den gennemprøvede
podcast-motor fra SaaS Købmænd. Den er med vilje **ikke klar til produktion**:
indhold, branding, feed-adresser, domæner og integrationer peger fortsat på
SaaS Købmænd, indtil punkterne i [MIGRATION.md](MIGRATION.md) er gennemført.

Der er ikke kopieret et deployment-workflow. Dermed kan starteren testes og
tilpasses uden risiko for at uploade SaaS Købmænd-sitet til det nye webhotel.

## Det vi genbruger

- RSS-hentning, cache og fallback-snapshot
- episodeoversigt og individuelle episode-URL'er
- lydafspiller og valgfri YouTube-video
- automatisk YouTube-match og permanent katalog
- sitemap, \`llms.txt\`, Open Graph og schema.org
- værtsprofiler, del-links og relaterede episoder
- PHP 7.4/8.4-tests

## Lokal test

Kræver PHP 7.4+ med \`mbstring\`, \`SimpleXML\` og \`DOM\`.

\`\`\`sh
bash tests/run-release.sh
\`\`\`

Testene bruger det kopierede SaaS Købmænd-snapshot, indtil Bo Møller-podcastens
feed og fixtures er sat ind.
