# Voorgebouwde images op de NUC

GitHub Actions bouwt bij iedere push naar main Linux/amd64-images en publiceert ze in GitHub Container Registry (GHCR).
De bestaande Dockerfiles en hun buildcontroles worden gebruikt. Applicatiesleutels blijven op de NUC; ze zijn niet nodig voor het bouwen.

## Eenmalig inloggen

Wacht eerst totdat de workflow **Publish container images** voor beide apps groen is.
Privépackages vereisen een GitHub personal access token (classic) met read:packages en toegang tot de betreffende packages.
Gebruik geen token met schrijfrechten als alleen downloaden nodig is.
Maak het token in GitHub Settings → Developer settings → Personal access tokens → Tokens (classic).

Voer op de NUC uit:

```sh
docker login ghcr.io -u frekel
```

Plak het token alleen in de Password-prompt. Deel het token niet in chat of in Git.
Packages hoeven niet publiek gemaakt te worden. De workflow gebruikt zijn eigen GITHUB_TOKEN om te publiceren.
Als bestaand packagebeleid publiceren blokkeert, controleer dan de Actions-toegang van dat package.

## Bijwerken

Vanuit ~/docker:

```sh
git pull --ff-only
./up.sh --village
```

Voor Home IoT:

```sh
./up.sh --home-iot
```

Zonder argument werkt up.sh nog steeds de hele stack bij.
Village en Home IoT worden niet meer op de NUC gebouwd; andere bestaande lokale diensten behouden hun buildproces.

up.sh haalt eerst de applicatiecode op en gebruikt de tag sha-<volledige commit>.
Zo horen code en image bij elkaar. Als de workflow nog bezig is of faalt, kan die tag nog ontbreken:
de update stopt dan, zonder lokaal te bouwen. Wacht op een groene workflow en probeer opnieuw.
Images zijn ook beschikbaar met tag main; direct docker compose pull gebruikt standaard die tag.

## Images

- ghcr.io/frekel/ai-village-backend
- ghcr.io/frekel/ai-village-web
- ghcr.io/frekel/home-iot (dezelfde image voor de webapp en furniture-worker)

Iedere workflow bouwt alleen linux/amd64 voor NUC8i7BEH.
Er worden geen automatische hostdeployments of betaalde AI-aanroepen uitgevoerd.
GitHub Actions gebruikt wel de beschikbare buildminuten en package-/cache-opslag van het account.

## Rollback

Bewaar de vorige image-tag of digest en de bijbehorende configuratie.
Zet VILLAGE_IMAGE_TAG of HOME_IOT_IMAGE_TAG tijdelijk in ~/docker/.env.local op de gewenste oudere sha-tag.
up.sh respecteert deze expliciete keuze.
Bij mogelijke onverenigbare databasemigraties herstel je ook de consistente backup van die versie.
Verwijder geen Docker-volumes om images bij te werken.

Voor Village blijven SQLite en agentnotities in ai_village_data en ai_village_agents.
Voor Home IoT blijven de bestaande database- en storagevolumes behouden.

## Bronnen

- [GitHub: container registry en authenticatie](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry)
- [GitHub: images publiceren](https://docs.github.com/en/actions/tutorials/publish-packages/publish-docker-images)
- [Docker: GitHub Actions buildcache](https://docs.docker.com/build/cache/backends/gha/)
