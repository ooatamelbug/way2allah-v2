# Download lab: local production-like validation (disposable)

A local Docker environment for validating Khotab downloads behind **Apache prefork + php-cgi**. It is **not** a
deployment configuration, and it is never connected to production.

| Port | Handler |
|---|---|
| 8081 | **FastCGI** (`mod_fcgid` + `php-cgi`) |
| 8082 | **plain CGI** (`mod_cgi` + `Action` → `php-cgi`) |

Alongside it run MariaDB 10.11 and a scripted upstream source (`conf/source-server.php`). The data is synthetic only
(`conf/seed.php`). Results and their interpretation are in `docs/reports/download-local-fastcgi-validation.md`.

## Run (all local; disposable)

```sh
LAB=$(mktemp -d); cp -r tools/download-lab/. "$LAB"/
rsync -a --exclude '.env*' --exclude .git --exclude node_modules --exclude 'storage/' . "$LAB/app/"
mkdir -p "$LAB"/app/storage/{app/public,framework/{cache/data,sessions,views},logs} "$LAB"/data
# Fill env.lab.example's <generate locally> values (APP_KEY, DB password) into "$LAB/app/.env".
# Put synthetic files in "$LAB/data": lecture.mp3 (5 MiB), lecture.mp4 (3 MiB), transcript.pdf, archive.rar.
docker build -t w2a-lab-fcgi:local "$LAB"
docker network create w2a-lab-net
docker run -d --rm --name w2a-lab-db --network w2a-lab-net --tmpfs /var/lib/mysql \
  -e MARIADB_ROOT_PASSWORD=<pw> -e MARIADB_DATABASE=w2a_lab mariadb:10.11
docker volume create w2a-lab-app; docker volume create w2a-lab-data
docker run -d --rm --name w2a-lab-web --network w2a-lab-net --memory=4g --cpus=3 \
  -p 127.0.0.1:18081:8081 -p 127.0.0.1:18082:8082 -e LAB_MAX_WORKERS=150 -e LAB_FCGID_OUTPUT_BUFFER=0 \
  -v w2a-lab-app:/app -v w2a-lab-data:/data w2a-lab-fcgi:local
docker cp "$LAB/app/." w2a-lab-web:/app/; docker cp "$LAB/data/." w2a-lab-web:/data/
docker exec w2a-lab-web chown -R w2a:w2a /app /data
docker run -d --rm --name w2a-lab-source --network w2a-lab-net w2a-lab-fcgi:local sleep infinity
docker cp "$LAB/conf/source-server.php" w2a-lab-source:/tmp/
docker exec -d w2a-lab-source sh -c 'php /tmp/source-server.php > /tmp/source.log 2>&1'
docker cp "$LAB/conf/seed.php" w2a-lab-web:/tmp/; docker exec -u w2a w2a-lab-web php /tmp/seed.php
echo <pw> > "$LAB/.dbpw"
cd "$LAB" && python3 labtest.py s_behavior        # likewise: s_disconnect, s_concurrency, s_isolation
```

Bind mounts are avoided on purpose: the files go in through `docker cp` into named volumes.

## Teardown

```sh
docker rm -f w2a-lab-web w2a-lab-source w2a-lab-db
docker volume rm w2a-lab-app w2a-lab-data
docker network rm w2a-lab-net
```
